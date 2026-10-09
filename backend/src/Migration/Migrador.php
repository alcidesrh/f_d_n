<?php

namespace App\Migration;

use App\Entity\Enum\EstadoBoletoAsiento;
use App\Migration\Salida\DependenciasLegado;
use App\Migration\Salida\InferenciaBus;
use App\Migration\Salida\RutaMigrada;
use App\Migration\Salida\TrayectosLegado;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Migración dirigida por salida: cada salida del legado arrastra su árbol de
 * relaciones y se escribe en una transacción, en el orden que exigen las FK
 * del modelo nuevo:
 *
 *   empresa → estaciones + trayecto de la ruta (con sus subtrayectos)
 *   → bus (empresa, marca, clase, piloto, copiloto, asientos y señales)
 *   → salida (con su estado)
 *   → por boleto: estaciones y trayecto del tramo, pasajero y comprador
 *     (tipo de documento, nacionalidad), usuario (estación o agencia),
 *     agencia, tipo de pago, moneda, factura certificada → boleto_venta
 *     → boleto_asiento; al final, el total de cada venta.
 *
 * Es idempotente: una salida ya migrada se completa (estado, bus que se le
 * asignó después, boletos que faltan) en vez de saltarse. Lo que el modelo
 * nuevo no admite se omite y se cuenta (`omitido_*`), nunca en silencio.
 */
class Migrador
{
    /**
     * Mapa de estado_id legacy → EstadoBoletoAsiento. El legacy "Cancelado" (6)
     * no tiene equivalente propio en el enum nuevo; se resuelve como ANULADO
     * (el estado terminal más cercano semánticamente). Ajustar si el negocio
     * llega a distinguir ambos casos.
     */
    private const LEGACY_ESTADO_BOLETO_MAP = [
        1 => EstadoBoletoAsiento::EMITIDO,
        2 => EstadoBoletoAsiento::CHEQUEADO,
        3 => EstadoBoletoAsiento::TRANSITO,
        4 => EstadoBoletoAsiento::ANULADO,
        5 => EstadoBoletoAsiento::REASIGNADO,
        6 => EstadoBoletoAsiento::ANULADO,
    ];

    /** Estados que no ocupan el asiento: no cuentan en el total de la venta. */
    private const ESTADOS_LIBERAN = ["anulado", "reasignado"];

    /** Ejemplos por motivo de omisión que se escriben en el log. */
    private const EJEMPLOS_POR_MOTIVO = 5;

    /** @var array<int|string, int> factura_generada del legado → boleto_venta (esta ejecución) */
    private array $ventasPorFactura = [];

    /** @var array<int|string, array<string, mixed>|null> factura del legado (emisor) por id */
    private array $facturasLegado = [];

    /** @var array<string, list<string>> motivo → ejemplos */
    private array $ejemplos = [];

    public function __construct(
        private Connection $newConn,
        #[Target("oldPdo")] private \PDO $oldPdo,
        private Mapeador $mapeador,
        private DependenciasLegado $dependencias,
        private TrayectosLegado $trayectos,
        private InferenciaBus $inferencia,
    ) {
        $this->oldPdo->setAttribute(
            \PDO::ATTR_ERRMODE,
            \PDO::ERRMODE_EXCEPTION,
        );
        $this->oldPdo->setAttribute(
            \PDO::ATTR_DEFAULT_FETCH_MODE,
            \PDO::FETCH_ASSOC,
        );
    }

    /**
     * @param \Closure|null $onProgress Optional callback invoked every N iterations: fn(int $done, int $total) => void
     * @param \Closure|null $debeCancelar Optional callback consulted at the top of each iteration: fn() => bool
     * @param array|null    $salidasPrefetchadas Filas ya obtenidas (fetchSalidasPendientes / fetchSalidasRango): evita un segundo fetch
     *
     * @return array<string, int> contadores (`errores` = salidas revertidas)
     */
    public function migrarSalida(
        $salidas = 100,
        ?OutputInterface $output = null,
        ?\Closure $onProgress = null,
        ?\Closure $debeCancelar = null,
        ?array $salidasPrefetchadas = null,
    ): array {
        $contadores = $this->contadoresIniciales();
        $this->ejemplos = [];

        $salidas = $salidasPrefetchadas ?? $this->fetchSalidas($salidas);
        if ($output) {
            $output->writeln(
                sprintf("<info>Salidas a migrar: %d</info>", count($salidas)),
            );
        }

        $total = count($salidas);
        foreach ($salidas as $i => $salida) {
            if ($debeCancelar && $debeCancelar()) {
                if ($output) {
                    $output->writeln(
                        "<comment>Migración cancelada por el usuario.</comment>",
                    );
                }
                break;
            }

            $legacyId = (string) $salida["id"];
            $antes = $contadores;
            $marca = $this->dependencias->marcar();
            $ventas = $this->ventasPorFactura;

            $this->newConn->beginTransaction();
            try {
                $this->migrarArbolSalida($salida, $contadores);
                $this->newConn->commit();
            } catch (\Throwable $e) {
                $this->newConn->rollBack();
                // Lo escrito en la transacción se perdió: también lo que las cachés recuerdan.
                $contadores = $antes;
                $this->dependencias->restaurar($marca);
                $this->trayectos->olvidar();
                $this->ventasPorFactura = $ventas;
                $contadores["errores"]++;
                if ($output) {
                    $output->writeln(
                        sprintf(
                            "<error>Error salida %s: %s</error>",
                            $legacyId,
                            $e->getMessage(),
                        ),
                    );
                }
            }

            if (($i + 1) % 25 === 0) {
                if ($onProgress) {
                    $onProgress(min($i + 1, $total), $total);
                }
            }
            if ($output && ($i + 1) % 10 === 0) {
                $output->write(
                    sprintf(
                        "\r<info>Salidas... %d/%d</info>",
                        min($i + 1, $total),
                        $total,
                    ),
                );
            }
        }

        if ($output) {
            $output->writeln("");
        }

        $this->dependencias->reiniciarIdentidades();

        $contadores = array_merge($contadores, $this->dependencias->contadores());
        if ($output) {
            $this->escribirResumen($contadores, $output);
        }

        return $contadores;
    }

    // ─── Árbol de una salida ───────────────────────────────────────

    /**
     * @param array<string, mixed> $salida fila de `salida` + `ruta_codigo`, `it_empresa_id`
     * @param array<string, int>   $contadores
     */
    private function migrarArbolSalida(array $salida, array &$contadores): void
    {
        $legacyId = (string) $salida["id"];
        $empresaId = $this->dependencias->empresa(
            $salida["empresa_id"] ?: ($salida["it_empresa_id"] ?? null),
        );

        $ruta = $this->trayectos->deRuta($salida["ruta_codigo"] ?? null);
        if ($ruta === null) {
            // `salida.trayecto_id` es obligatorio: sin ruta no hay salida.
            $this->omitir($contadores, "salida_sin_trayecto", "salida {$legacyId} (ruta " . ($salida["ruta_codigo"] ?: "—") . ")");

            return;
        }

        $busId = $this->dependencias->bus($salida["bus_codigo"] ?? null, $empresaId);
        if ($busId === null && !empty($salida["bus_codigo"])) {
            $this->omitir($contadores, "bus_no_migrable", "bus {$salida["bus_codigo"]} (salida {$legacyId})");
        }

        $data = $this->mapeador->salida($salida, $busId, $empresaId, $ruta->trayectoId);
        $existente = $this->newConn->fetchAssociative(
            "SELECT s.id, s.bus_id, s.estado, i.bus_id AS inferido FROM salida s
               LEFT JOIN salida_bus_inferido i ON i.salida_id = s.id
              WHERE s.legacy_id = :lid",
            ["lid" => $legacyId],
        );
        $boletos = $this->fetchBoletosPorSalida((int) $salida["id"]);

        // Toda salida tiene bus (ADR-027). Si el legado no se lo asignó, se infiere.
        $inferencia = null;
        if ($busId === null && ($existente === false || $existente["bus_id"] === null)) {
            if ($boletos === [] && new \DateTimeImmutable((string) $data["fecha"]) < $this->ahora()) {
                $this->omitir($contadores, "salida_pasada_sin_bus", "salida {$legacyId}");

                return;
            }
            $inferencia = $empresaId === null
                ? ["motivo" => "sin_empresa"]
                : $this->inferencia->inferir($salida, $empresaId, $ruta->trayectoId, $this->ahora());
            if (isset($inferencia["motivo"])) {
                $this->omitir($contadores, "salida_sin_bus_inferible", "salida {$legacyId} ({$inferencia["motivo"]}, " . count($boletos) . " boletos)");

                return;
            }
            $busId = $inferencia["bus"];
            $data["bus_id"] = $busId;
            $this->contar($contadores, "bus_inferido_" . $inferencia["criterio"]);
        }

        if ($existente === false) {
            $salidaId = (int) $this->newConn->fetchOne(
                "INSERT INTO salida (fecha, bus_id, empresa_id, trayecto_id, estado, legacy_id, created_at, updated_at)
                 VALUES (:fecha, :bus_id, :empresa_id, :trayecto_id, :estado, :legacy_id, NOW(), NOW()) RETURNING id",
                $data,
            );
            $contadores["salida"]++;
        } else {
            // Ya migrada: se completa con lo que cambió en el legado (estado,
            // bus asignado después). Un bus ya puesto no se reemplaza, salvo
            // el que infirió la migración cuando el legado asigna el real.
            $salidaId = (int) $existente["id"];
            if ($existente["inferido"] !== null && $busId !== null) {
                $busId = $this->busRealTrasInferido($salidaId, (int) $existente["bus_id"], $busId, $legacyId, $contadores);
            } elseif ($existente["bus_id"] !== null) {
                $busId = (int) $existente["bus_id"];
            }
            if ($existente["estado"] !== $data["estado"] || ($existente["bus_id"] === null && $busId !== null)) {
                $this->newConn->executeStatement(
                    "UPDATE salida SET estado = :estado, bus_id = :bus WHERE id = :id",
                    ["estado" => $data["estado"], "bus" => $busId, "id" => $salidaId],
                );
                $contadores["salida_actualizada"]++;
            }
        }
        if ($inferencia !== null) {
            $this->newConn->executeStatement(
                "INSERT INTO salida_bus_inferido (salida_id, bus_id, criterio, referencia_legado, creado_en)
                 VALUES (:salida, :bus, :criterio, :referencia, NOW())
                 ON CONFLICT (salida_id) DO UPDATE SET bus_id = EXCLUDED.bus_id, criterio = EXCLUDED.criterio, referencia_legado = EXCLUDED.referencia_legado",
                ["salida" => $salidaId, "bus" => $busId, "criterio" => $inferencia["criterio"], "referencia" => $inferencia["referencia"]],
            );
        }

        $this->migrarBoletosDeSalida(
            (int) $salida["id"],
            $boletos,
            $salidaId,
            $ruta,
            $busId,
            $empresaId,
            $contadores,
        );
    }

    /**
     * El legado asignó el bus real a una salida cuyo bus infirió la migración.
     * Con el mismo croquis, la salida pasa al bus real y sus boletos (y
     * reservas) a los asientos del mismo número; con otro croquis se conserva
     * el inferido y se reporta para reasignar a mano. Devuelve el bus que queda.
     *
     * @param array<string, int> $contadores
     */
    private function busRealTrasInferido(int $salidaId, int $inferido, int $real, string $legacyId, array &$contadores): int
    {
        if ($inferido !== $real) {
            $mismoCroquis = $this->newConn->fetchOne(
                "SELECT 1 FROM bus a JOIN bus b ON b.croquis_id = a.croquis_id WHERE a.id = :a AND b.id = :b",
                ["a" => $inferido, "b" => $real],
            ) !== false;
            if (!$mismoCroquis) {
                $this->omitir($contadores, "bus_real_otro_croquis", "salida {$legacyId}: se conserva el bus inferido");

                return $inferido;
            }
            foreach (["boleto_asiento", "reserva_asiento"] as $tabla) {
                $this->newConn->executeStatement(
                    "UPDATE {$tabla} t SET asiento_id = nuevo.id
                       FROM asiento viejo, asiento nuevo
                      WHERE t.salida_id = :salida AND viejo.id = t.asiento_id
                        AND nuevo.bus_id = :real AND nuevo.numero = viejo.numero",
                    ["salida" => $salidaId, "real" => $real],
                );
            }
            $this->newConn->executeStatement("UPDATE salida SET bus_id = :bus WHERE id = :id", ["bus" => $real, "id" => $salidaId]);
            $this->contar($contadores, "bus_inferido_reemplazado");
        } else {
            $this->contar($contadores, "bus_inferido_confirmado");
        }
        $this->newConn->executeStatement("DELETE FROM salida_bus_inferido WHERE salida_id = :s", ["s" => $salidaId]);

        return $real;
    }

    /** @param array<string, int> $contadores */
    private function contar(array &$contadores, string $clave): void
    {
        $contadores[$clave] = ($contadores[$clave] ?? 0) + 1;
    }

    private function ahora(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }

    /**
     * Boletos de la salida. Los que ocupan el asiento van primero: si el
     * legado anuló un asiento y lo volvió a vender en el mismo tramo, el
     * modelo nuevo admite uno solo por `(asiento, trayecto, salida)` y se
     * conserva el vigente.
     *
     * @param array<string, int> $contadores
     */
    private function migrarBoletosDeSalida(
        int $salidaLegado,
        array $boletos,
        int $salidaId,
        RutaMigrada $ruta,
        ?int $busId,
        ?int $empresaId,
        array &$contadores,
    ): void {
        if ($boletos === []) {
            return;
        }

        $migrados = array_flip($this->newConn->fetchFirstColumn(
            "SELECT legacy_id FROM boleto_asiento WHERE salida_id = :s AND legacy_id IS NOT NULL",
            ["s" => $salidaId],
        ));
        $ocupados = [];
        foreach ($this->newConn->fetchAllNumeric(
            "SELECT asiento_id, trayecto_id FROM boleto_asiento WHERE salida_id = :s",
            ["s" => $salidaId],
        ) as [$a, $t]) {
            $ocupados["{$a}:{$t}"] = true;
        }

        $this->dependencias->precargarClientes([
            ...array_column($boletos, "cliente_boleto"),
            ...array_column($boletos, "cliente_documento"),
        ]);

        $ventasTocadas = [];
        foreach ($boletos as $old) {
            $lid = (string) $old["id"];
            if (isset($migrados[$lid])) {
                continue;
            }
            if ($busId === null) {
                // En el modelo nuevo el asiento es del bus y no se vende sin bus (ReglasVenta).
                $this->omitir($contadores, "boleto_sin_bus", "boleto {$lid} (salida {$salidaLegado})");
                continue;
            }
            $asientoId = $this->dependencias->asiento($busId, $old["asiento_numero"] ?? null);
            if ($asientoId === null) {
                $this->omitir($contadores, "boleto_sin_asiento", "boleto {$lid}: asiento " . ($old["asiento_numero"] ?? "—") . " no está en el croquis del bus");
                continue;
            }

            $trayectoId = $this->trayectoDelBoleto($old, $ruta, $contadores);
            if ($trayectoId === null) {
                $this->omitir($contadores, "boleto_sin_trayecto", "boleto {$lid}");
                continue;
            }
            if (isset($ocupados["{$asientoId}:{$trayectoId}"])) {
                $this->omitir($contadores, "boleto_asiento_repetido", sprintf(
                    "boleto %s (%s): asiento %s ya vendido en el mismo tramo",
                    $lid,
                    $this->resolverEstadoBoletoAsiento($old),
                    $old["asiento_numero"],
                ));
                continue;
            }

            $pasajero = $this->dependencias->cliente($old["cliente_boleto"] ?? null)
                ?? $this->dependencias->cliente($old["cliente_documento"] ?? null);
            if ($pasajero === null) {
                $this->omitir($contadores, "boleto_sin_cliente", "boleto {$lid}");
                continue;
            }

            $ventaId = $this->ventaDelBoleto($old, $pasajero, $empresaId, $contadores);
            $this->newConn->executeStatement(
                'INSERT INTO boleto_asiento (salida_id, asiento_id, cliente_id, trayecto_id, estado, boleto_venta_id, precio_monto, precio_moneda, observacion, legacy_id)
                 VALUES (:salida_id, :asiento_id, :cliente_id, :trayecto_id, :estado, :boleto_venta_id, :precio_monto, :precio_moneda, :observacion, :legacy_id)',
                $this->mapeador->boletoAsiento(
                    $old,
                    $salidaId,
                    $asientoId,
                    $pasajero,
                    $trayectoId,
                    $this->resolverEstadoBoletoAsiento($old),
                    $ventaId,
                ),
            );
            $ocupados["{$asientoId}:{$trayectoId}"] = true;
            $ventasTocadas[$ventaId] = true;
            $contadores["boleto_asiento"]++;
        }

        if ($ventasTocadas !== []) {
            $this->newConn->executeStatement(
                "UPDATE boleto_venta v SET total_monto = COALESCE((
                     SELECT SUM(b.precio_monto) FROM boleto_asiento b
                     WHERE b.boleto_venta_id = v.id AND b.estado NOT IN (:libres)), 0)
                 WHERE v.id IN (:ids)",
                ["libres" => self::ESTADOS_LIBERAN, "ids" => array_keys($ventasTocadas)],
                ["libres" => ArrayParameterType::STRING, "ids" => ArrayParameterType::INTEGER],
            );
        }
    }

    /**
     * Trayecto del tramo del boleto (`estacion_origen_id` → `estacion_destino_id`):
     * el par de la ruta o, si alguna estación no está en ella, el trayecto
     * suelto de ese par.
     *
     * @param array<string, int> $contadores
     */
    private function trayectoDelBoleto(array $old, RutaMigrada $ruta, array &$contadores): ?int
    {
        $origen = $this->dependencias->estacion($old["estacion_origen_id"] ?? null) ?? $ruta->origenId;
        $destino = $this->dependencias->estacion($old["estacion_destino_id"] ?? null) ?? $ruta->destinoId;
        if ($origen === $destino) {
            return null;
        }
        $id = $ruta->entre($origen, $destino);
        if ($id !== null) {
            return $id;
        }
        $contadores["boleto_tramo_fuera_de_ruta"]++;

        return $this->trayectos->suelto($origen, $destino);
    }

    /**
     * Venta del boleto: la de su factura del legado si ya se migró (una
     * factura agrupa los boletos de una venta) o una nueva, con su factura
     * certificada.
     *
     * @param array<string, int> $contadores
     */
    private function ventaDelBoleto(array $old, int $pasajero, ?int $empresaSalida, array &$contadores): int
    {
        $fg = $old["factura_generada_id"] ?? null;
        if ($fg !== null && isset($this->ventasPorFactura[$fg])) {
            return $this->ventasPorFactura[$fg];
        }

        $comprador = $this->dependencias->cliente($old["cliente_documento"] ?? null) ?? $pasajero;
        $facturaId = null;
        if (Mapeador::facturaCertificada($old)) {
            $uuid = strtolower(trim((string) $old["fg_uuid"]));
            $ventaExistente = $this->newConn->fetchOne(
                "SELECT v.id FROM boleto_venta v JOIN factura f ON f.id = v.factura_id WHERE f.uuid = :uuid",
                ["uuid" => $uuid],
            );
            if ($ventaExistente !== false) {
                return $this->ventasPorFactura[$fg] = (int) $ventaExistente;
            }
            $facturaId = $this->crearFactura($old, $comprador, $empresaSalida, $contadores);
        }

        $canal = Mapeador::canalVenta($old);
        $data = $this->mapeador->boletoVenta($old, [
            "usuario" => $this->dependencias->usuario($old["usuario_creacion_id"] ?? null),
            "cliente" => $comprador,
            "estacion" => $canal->value === "estacion" ? $this->dependencias->estacion($old["estacion_creacion_id"] ?? null) : null,
            "agencia" => $canal->value === "agencia" ? $this->dependencias->agencia($old["estacion_creacion_id"] ?? null) : null,
            "tipo_pago" => $this->dependencias->tipoPago($old["tipo_pago_id"] ?? null),
            "moneda" => $this->dependencias->moneda($old["moneda_id"] ?? null),
        ], $facturaId);
        $campos = implode(", ", array_keys($data));
        $args = implode(", ", array_map(static fn($k) => ":{$k}", array_keys($data)));
        $ventaId = (int) $this->newConn->fetchOne(
            "INSERT INTO boleto_venta ({$campos}) VALUES ({$args}) RETURNING id",
            $data,
            ["cortesia" => \Doctrine\DBAL\ParameterType::BOOLEAN, "voucher" => \Doctrine\DBAL\ParameterType::BOOLEAN],
        );
        $contadores["boleto_venta"]++;

        if ($fg !== null) {
            $this->ventasPorFactura[$fg] = $ventaId;
        }

        return $ventaId;
    }

    /** @param array<string, int> $contadores */
    private function crearFactura(array $old, int $comprador, ?int $empresaSalida, array &$contadores): ?int
    {
        $emisor = $this->dependencias->empresa($this->facturaLegado($old["fg_factura_id"] ?? null)["empresa_id"] ?? null)
            ?? $empresaSalida;
        $datosEmisor = $emisor === null ? false : $this->newConn->fetchAssociative(
            "SELECT nit, nombre, nombre_comercial FROM empresa WHERE id = :id",
            ["id" => $emisor],
        );
        $data = $this->mapeador->factura(
            $old,
            $datosEmisor ?: ["nit" => null, "nombre" => "Sin emisor", "nombre_comercial" => null],
            $this->dependencias->datosCliente($comprador),
        );
        if ($data === null) {
            return null;
        }
        $campos = implode(", ", array_keys($data));
        $args = implode(", ", array_map(static fn($k) => ":{$k}", array_keys($data)));
        $id = (int) $this->newConn->fetchOne("INSERT INTO factura ({$campos}) VALUES ({$args}) RETURNING id", $data);
        $contadores["factura"]++;

        return $id;
    }

    /** Fila de `factura` del legado (serie/resolución de la empresa emisora). */
    private function facturaLegado(mixed $id): ?array
    {
        if (!is_numeric($id)) {
            return null;
        }
        if (!array_key_exists($id, $this->facturasLegado)) {
            $this->facturasLegado[$id] = $this->fetchOld("SELECT id, empresa_id FROM factura WHERE id = :id", ["id" => $id])[0] ?? null;
        }

        return $this->facturasLegado[$id];
    }

    /** @param array<string, int> $contadores */
    private function omitir(array &$contadores, string $motivo, string $ejemplo): void
    {
        $contadores["omitido_{$motivo}"] = ($contadores["omitido_{$motivo}"] ?? 0) + 1;
        if (count($this->ejemplos[$motivo] ?? []) < self::EJEMPLOS_POR_MOTIVO) {
            $this->ejemplos[$motivo][] = $ejemplo;
        }
    }

    /** @param array<string, int> $contadores */
    private function escribirResumen(array $contadores, OutputInterface $output): void
    {
        $output->writeln("<info>Resumen:</info>");
        foreach ($contadores as $clave => $n) {
            if ($n > 0 && !str_starts_with($clave, "omitido_")) {
                $output->writeln(sprintf("  %-28s %d", $clave, $n));
            }
        }
        foreach ($contadores as $clave => $n) {
            if ($n > 0 && str_starts_with($clave, "omitido_")) {
                $motivo = substr($clave, strlen("omitido_"));
                $output->writeln(sprintf("<comment>  Omitidos (%s): %d — p. ej. %s</comment>", $motivo, $n, implode("; ", $this->ejemplos[$motivo] ?? [])));
            }
        }
    }

    // ─── Existence checks ──────────────────────────────────────────

    /**
     * Check if a record exists by legacy_id (or by id for tables that keep the legacy PK).
     */
    public function yaMigrado(string $tabla, string $legacyId): bool
    {
        $sql = match ($tabla) {
            "estacion" => "SELECT 1 FROM enclave WHERE id = :lid",
            "empresa", "cliente", "usuario"
                => "SELECT 1 FROM {$tabla} WHERE id = :lid",
            "bus" => "SELECT 1 FROM {$tabla} WHERE codigo = :lid",
            default => "SELECT 1 FROM {$tabla} WHERE legacy_id = :lid",
        };
        $result = $this->newConn->fetchOne($sql, ["lid" => $legacyId]);

        return $result !== false;
    }

    // ─── Fetch helpers ─────────────────────────────────────────────

    private function sanitizeUtf8(array $row): array
    {
        $clean = [];
        foreach ($row as $k => $v) {
            $clean[$k] = is_string($v)
                ? mb_convert_encoding($v, "UTF-8", "ISO-8859-1")
                : $v;
        }
        return $clean;
    }

    private function fetchOld(string $sql, array $params = []): array
    {
        $stmt = $this->oldPdo->prepare($sql);
        $stmt->execute($params);
        return array_map(
            fn(array $r) => $this->sanitizeUtf8($r),
            $stmt->fetchAll(),
        );
    }

    /** Columnas comunes de las consultas de salidas del legado. */
    private const SELECT_SALIDA = "SELECT s.*, i.ruta_codigo, i.tipo_bus_id AS it_tipo_bus_id, i.empresa_id AS it_empresa_id
             FROM salida s
             LEFT JOIN itineario i ON i.id = s.itinerario_id
             WHERE 1 = 1";

    /**
     * Filtro de fechas (Y-m-d) con `hasta` inclusive: `fecha` es fecha y hora,
     * así que una salida del día `hasta` a las 20:00 también entra.
     *
     * @param array<string, string> $params
     */
    private static function filtroFechas(?string $desde, ?string $hasta, array &$params): string
    {
        $sql = "";
        if ($desde) {
            $sql .= " AND s.fecha >= CAST(:desde AS date)";
            $params["desde"] = $desde;
        }
        if ($hasta) {
            $sql .= " AND s.fecha < DATEADD(day, 1, CAST(:hasta AS date))";
            $params["hasta"] = $hasta;
        }

        return $sql;
    }

    private function fetchSalidas(
        int $salidas,
        ?string $desde = null,
        ?string $hasta = null,
    ): array {
        $params = [];
        $sql = str_replace("SELECT s.*", "SELECT TOP {$salidas} s.*", self::SELECT_SALIDA)
            . self::filtroFechas($desde, $hasta, $params)
            . " ORDER BY s.fecha DESC";

        return $this->fetchOld($sql, $params);
    }

    /**
     * Todas las salidas del legado con fecha en [desde, hasta] (cualquier
     * estado: programadas, canceladas, finalizadas…), migradas o no: migrar un
     * rango completa también las que ya estaban.
     */
    public function fetchSalidasRango(string $desde, string $hasta): array
    {
        $params = [];

        return $this->fetchOld(
            self::SELECT_SALIDA . self::filtroFechas($desde, $hasta, $params) . " ORDER BY s.fecha ASC, s.id ASC",
            $params,
        );
    }

    private function fetchSalidasVentana(
        int $cantidad,
        int $offset,
        ?string $desde = null,
        ?string $hasta = null,
    ): array {
        $params = [];
        $sql = self::SELECT_SALIDA
            . self::filtroFechas($desde, $hasta, $params)
            . " ORDER BY s.fecha DESC OFFSET {$offset} ROWS FETCH NEXT {$cantidad} ROWS ONLY";

        return $this->fetchOld($sql, $params);
    }

    /**
     * Salidas del legado aún no migradas (fecha DESC), para que la operación
     * "migrar N más" avance entre ejecuciones sucesivas sin depender de un TOP fijo.
     */
    public function fetchSalidasPendientes(
        int $cantidad,
        ?string $desde = null,
        ?string $hasta = null,
    ): array {
        // Set de legacy_id ya migrados en el nuevo salida (evita N+1).
        $migradas = [];
        $rows = $this->newConn
            ->executeQuery(
                "SELECT legacy_id FROM salida WHERE legacy_id IS NOT NULL",
            )
            ->fetchFirstColumn();
        foreach ($rows as $legacyId) {
            $migradas[(string) $legacyId] = true;
        }

        $pendientes = [];
        $ventana = max(100, min($cantidad * 2, 500));
        $offset = 0;
        // Techo de seguridad: nunca escanear más de 20× la cantidad pedida.
        $maxEscaneadas = max(2000, $cantidad * 20);

        while (count($pendientes) < $cantidad && $maxEscaneadas > 0) {
            $rows = $this->fetchSalidasVentana(
                $ventana,
                $offset,
                $desde,
                $hasta,
            );
            if ([] === $rows) {
                break;
            }
            foreach ($rows as $row) {
                $maxEscaneadas--;
                if (!isset($migradas[(string) $row["id"]])) {
                    $pendientes[] = $row;
                    if (count($pendientes) >= $cantidad) {
                        break 2;
                    }
                }
            }
            if (count($rows) < $ventana) {
                break; // última página
            }
            $offset += $ventana;
        }

        return $pendientes;
    }

    /**
     * Total de salidas fuente (legado) con los mismos filtros que fetchSalidas.
     */
    public function contarSalidas(
        ?string $desde = null,
        ?string $hasta = null,
    ): int {
        $params = [];
        $stmt = $this->oldPdo->prepare(
            "SELECT COUNT(*) FROM salida s WHERE 1 = 1" . self::filtroFechas($desde, $hasta, $params),
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Marca `boleto_venta.voucher` en boletos ya migrados (por
     * `boleto_asiento.legacy_id`), sin migrar de nuevo. Devuelve los marcados.
     */
    public function actualizarVouchers(): int
    {
        $count = 0;
        $ids = [];
        $marcar = function () use (&$ids, &$count) {
            if ($ids === []) {
                return;
            }
            $count += $this->newConn->executeStatement(
                "UPDATE boleto_venta v SET voucher = true FROM boleto_asiento b
                 WHERE b.boleto_venta_id = v.id AND b.legacy_id IN (:ids) AND v.voucher = false",
                ["ids" => $ids],
                ["ids" => \Doctrine\DBAL\ArrayParameterType::STRING],
            );
            $ids = [];
        };
        foreach ($this->fetchOld(
            "SELECT id FROM boleto WHERE voucher_estacion_id IS NOT NULL OR voucher_agencia_id IS NOT NULL OR voucher_internet_id IS NOT NULL",
        ) as $row) {
            $ids[] = (string) $row["id"];
            if (count($ids) >= 1000) {
                $marcar();
            }
        }
        $marcar();

        return $count;
    }

    /**
     * Resuelve el EstadoBoletoAsiento a partir del estado_id legacy del boleto.
     * Estado desconocido o ausente → fallback EMITIDO.
     */
    private function resolverEstadoBoletoAsiento(array $boletoOld): string
    {
        $estadoId = (int) ($boletoOld["estado_id"] ?? 0);
        $estado =
            self::LEGACY_ESTADO_BOLETO_MAP[$estadoId] ??
            EstadoBoletoAsiento::EMITIDO;

        return $estado->value;
    }

    /**
     * Boletos de una salida con lo que su árbol necesita del legado: número
     * del asiento, tipo de la estación que lo emitió (agencia) y la factura
     * (`fg_*`). Primero los que ocupan el asiento (no anulados, reasignados
     * ni cancelados).
     */
    private function fetchBoletosPorSalida(int $salidaId): array
    {
        return $this->fetchOld(
            "SELECT b.*, ba.numero AS asiento_numero, ec.tipoEstacion_id AS creacion_tipo,
                    fg.factura_id AS fg_factura_id, fg.sAutorizacionUUIDsat AS fg_uuid,
                    fg.sNumeroDTEsat AS fg_dte, fg.sSerieDTEsat AS fg_serie,
                    CONVERT(varchar(19), fg.fecha, 120) AS fg_fecha,
                    CONVERT(varchar(19), fg.sFechaCertificaDTEsat, 120) AS fg_certificada,
                    fg.importeTotal AS fg_total, fg.autorizacionTarjeta AS fg_autorizacion,
                    CONVERT(varchar(19), b.fecha_creacion, 120) AS fecha_creacion,
                    CONVERT(varchar(19), b.fecha_actualizacion, 120) AS fecha_actualizacion
               FROM boleto b
               LEFT JOIN bus_asiento ba ON ba.id = b.asiento_bus_id
               LEFT JOIN estacion ec ON ec.id = b.estacion_creacion_id
               LEFT JOIN factura_generada fg ON fg.id = b.factura_generada_id
              WHERE b.salida_id = :salidaId
              ORDER BY CASE WHEN b.estado_id IN (4, 5, 6) THEN 1 ELSE 0 END, b.id",
            ["salidaId" => $salidaId],
        );
    }

    /** @return array<string, int> */
    private function contadoresIniciales(): array
    {
        return [
            "salida" => 0,
            "salida_actualizada" => 0,
            "boleto_asiento" => 0,
            "boleto_venta" => 0,
            "factura" => 0,
            "boleto_tramo_fuera_de_ruta" => 0,
            "errores" => 0,
        ];
    }
}
