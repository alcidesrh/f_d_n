<?php

namespace App\Migration;

use App\Entity\Enum\EstadoBoletoAsiento;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

class Migrador
{
    /**
     * Tables that use old PK as new id (no legacy_id column).
     */
    private const ID_MAP = [
        "empresa",
        "enclave",
        "asiento",
        "cliente",
        "usuario",
    ];

    /**
     * Tables that keep legacy_id column (old PK is string or variable data).
     */
    private const LEGACY_MAP = ["bus", "trayecto", "salida", "boleto_asiento"];

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

    public function __construct(
        private Connection $newConn,
        #[Target("oldPdo")] private \PDO $oldPdo,
        private Mapeador $mapeador,
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
     * @param array|null    $salidasPrefetchadas Filas ya obtenidas (fetchSalidasPendientes): evita un segundo fetch
     */
    public function migrarSalida(
        $salidas = 100,
        ?OutputInterface $output = null,
        ?\Closure $onProgress = null,
        ?\Closure $debeCancelar = null,
        ?array $salidasPrefetchadas = null,
    ): array {
        $contadores = $this->contadoresIniciales();

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
            if ($this->yaMigrado("recorrido", $legacyId)) {
                continue;
            }

            $this->newConn->beginTransaction();
            try {
                $empresaId = $this->migrarEmpresa(
                    $salida["empresa_id"] ?: $salida["it_empresa_id"] ?? null,
                    $contadores,
                );

                $trayectoId = $this->migrarTrayecto(
                    $salida["ruta_codigo"] ?: null,
                    $contadores,
                );
                if (!$trayectoId) {
                    $this->newConn->rollBack();
                    if ($output) {
                        $output->writeln(
                            sprintf(
                                "<comment>Salida %s sin trayecto resoluble; omitida</comment>",
                                $legacyId,
                            ),
                        );
                    }
                    continue;
                }

                $busId = null;
                if (!empty($salida["bus_codigo"])) {
                    $busId = $this->migrarBus(
                        $salida["bus_codigo"],
                        $empresaId,
                        $contadores,
                    );
                    if ($busId) {
                        $this->migrarAsientosParaBus(
                            $salida["bus_codigo"],
                            $busId,
                            $contadores,
                        );
                    }
                }

                $nuevaSalidaId = $this->crearSalida(
                    $salida,
                    $busId,
                    $empresaId,
                    $trayectoId,
                    $contadores,
                );

                if ($nuevaSalidaId) {
                    $this->migrarBoletosDeSalida(
                        $salida["id"],
                        $nuevaSalidaId,
                        $trayectoId,
                        $busId,
                        $contadores,
                    );
                }

                $this->newConn->commit();
            } catch (\Throwable $e) {
                $this->newConn->rollBack();
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

        return $contadores;
    }

    // ─── Existence checks ──────────────────────────────────────────

    /**
     * Check if a record exists by legacy_id (or by id for ID_MAP tables).
     */
    public function yaMigrado(string $tabla, string $legacyId): bool
    {
        $sql = match ($tabla) {
            "estacion" => "SELECT 1 FROM enclave WHERE id = :lid",
            "empresa",
            "asiento",
            "cliente",
            "usuario"
                => "SELECT 1 FROM {$tabla} WHERE id = :lid",
            "bus" => "SELECT 1 FROM {$tabla} WHERE codigo = :lid",
            default => "SELECT 1 FROM {$tabla} WHERE legacy_id = :lid",
        };
        $result = $this->newConn->fetchOne($sql, ["lid" => $legacyId]);

        return $result !== false;
    }

    /**
     * Get the new DB id for a legacy record, searching by legacy_id or by id.
     */
    public function getNewId(
        string $tabla,
        string $legacyId,
        array $fields = ["id"],
    ): int|array|null {
        $select = implode(", ", $fields);

        $sql = match (true) {
            in_array($tabla, self::ID_MAP, true) || $tabla === "estacion"
                => match ($tabla) {
                "estacion" => "SELECT {$select} FROM enclave WHERE id = :lid",
                default
                    => "SELECT {$select} FROM \"{$tabla}\" WHERE id = :lid",
            },
            $tabla === "bus" => "SELECT {$select} FROM bus WHERE codigo = :lid",
            default
                => "SELECT {$select} FROM \"{$tabla}\" WHERE legacy_id = :lid",
        };

        if (is_numeric($legacyId)) {
            $sql .= " OR id = :lid";
        }

        if (count($fields) > 1 || $fields[0] !== "id") {
            $result = $this->newConn->fetchAllAssociative($sql, [
                "lid" => $legacyId,
            ]);
            return $result[0] ?? null;
        }
        $result = $this->newConn->fetchOne($sql, ["lid" => $legacyId]);
        return $result !== false ? (int) $result : null;
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

    private function fetchUsuario(int|string $id): ?array
    {
        $result = $this->fetchOld("SELECT * FROM custom_user WHERE id = :id", [
            "id" => $id,
        ]);
        return $result[0] ?? null;
    }

    private function fetchRuta(string $codigo): ?array
    {
        $result = $this->fetchOld("SELECT * FROM ruta WHERE codigo = :codigo", [
            "codigo" => $codigo,
        ]);
        return $result[0] ?? null;
    }

    private function fetchRutaEstacionItems(string $rutaCodigo): array
    {
        return $this->fetchOld(
            "SELECT * FROM ruta_estacion_item WHERE ruta_codigo = :codigo ORDER BY posicion ASC",
            ["codigo" => $rutaCodigo],
        );
    }

    private function fetchEmpresa(int|string $id): ?array
    {
        $result = $this->fetchOld("SELECT * FROM empresa WHERE id = :id", [
            "id" => $id,
        ]);
        return $result[0] ?? null;
    }

    private function fetchEstacion(int|string $id): ?array
    {
        $result = $this->fetchOld("SELECT * FROM estacion WHERE id = :id", [
            "id" => $id,
        ]);
        return $result[0] ?? null;
    }

    private function fetchBus(string $codigo): ?array
    {
        $result = $this->fetchOld("SELECT * FROM bus WHERE codigo = :codigo", [
            "codigo" => $codigo,
        ]);
        return $result[0] ?? null;
    }

    private function fetchAsientosPorTipoBus(int|string $tipoBusId): array
    {
        return $this->fetchOld(
            "SELECT ba.*, ca.nombre AS clase_nombre FROM bus_asiento ba LEFT JOIN clase_asiento ca ON ca.id = ba.clase_id WHERE ba.tipoBus_id = :id",
            ["id" => $tipoBusId],
        );
    }

    private function fetchCliente(int|string $id): ?array
    {
        $result = $this->fetchOld("SELECT * FROM cliente WHERE id = :id", [
            "id" => $id,
        ]);
        return $result[0] ?? null;
    }

    private function fetchTarifaBoletoPorRuta(
        ?int $origenId,
        ?int $destinoId,
    ): ?array {
        if (!$origenId || !$destinoId) {
            return null;
        }
        $result = $this->fetchOld(
            "SELECT TOP 1 * FROM tarifas_boleto WHERE estacion_origen_id = :origen AND estacion_destino_id = :destino ORDER BY fechaEfectividad DESC",
            ["origen" => $origenId, "destino" => $destinoId],
        );
        return $result[0] ?? null;
    }

    private function fetchTipoBusPorBus(string $busCodigo): ?array
    {
        $result = $this->fetchOld(
            "SELECT bt.* FROM bus_tipo bt INNER JOIN bus b ON b.tipo_id = bt.id WHERE b.codigo = :codigo",
            ["codigo" => $busCodigo],
        );
        return $result[0] ?? null;
    }

    // ─── Migration core ────────────────────────────────────────────

    private function migrarEmpresa(?int $oldId, array &$contadores): ?int
    {
        if (!$oldId) {
            return null;
        }

        $legacyId = (string) $oldId;
        if ($this->yaMigrado("empresa", $legacyId)) {
            return (int) $oldId;
        }

        $old = $this->fetchEmpresa($oldId);
        if (!$old) {
            return null;
        }

        $data = $this->mapeador->empresa($old);
        $this->newConn->executeStatement(
            "INSERT INTO empresa (id, nombre, nit, direccion, telefono, email) VALUES (:id, :nombre, :nit, :direccion, :telefono, :email)",
            $data,
        );
        $contadores["empresa"]++;

        return (int) $data["id"];
    }

    private function migrarEstacions(?int $oldId, array &$contadores): ?int
    {
        if (!$oldId) {
            return null;
        }

        $legacyId = (string) $oldId;
        if ($this->yaMigrado("estacion", $legacyId)) {
            return (int) $oldId;
        }

        $old = $this->fetchEstacion($oldId);
        if (!$old) {
            return null;
        }

        $data = $this->mapeador->estacion($old);
        $this->newConn->executeStatement(
            "INSERT INTO enclave (id, tipo, nombre, direccion, latitud, longitud) VALUES (:id, 'estacion', :nombre, :direccion, :latitud, :longitud)",
            $data,
        );
        $contadores["estacion"]++;

        return (int) $data["id"];
    }

    private function migrarBus(
        string $codigo,
        ?int $empresaId,
        array &$contadores,
    ): ?int {
        if (!$codigo || !$empresaId) {
            return null;
        }

        if ($this->yaMigrado("bus", $codigo)) {
            return $this->getNewId("bus", $codigo);
        }

        $old = $this->fetchBus($codigo);
        if (!$old) {
            return null;
        }

        $tipo = $this->fetchTipoBusPorBus($codigo);
        $data = $this->mapeador->bus($old, $empresaId);
        $data["gama"] = isset($tipo["descripcion"])
            ? mb_substr($tipo["descripcion"], 0, 50)
            : null;

        $fields = implode(", ", array_keys($data));
        $args = implode(", ", array_map(fn($k) => ":{$k}", array_keys($data)));
        $id = $this->newConn->fetchOne(
            "INSERT INTO bus ({$fields}) VALUES ({$args}) RETURNING id",
            $data,
        );
        $contadores["bus"]++;

        return (int) $id;
    }

    private function migrarAsientosParaBus(
        string $busCodigo,
        int $busId,
        array &$contadores,
    ): ?int {
        $tipo = $this->fetchTipoBusPorBus($busCodigo);
        if (!$tipo) {
            return null;
        }

        $asientosOld = $this->fetchAsientosPorTipoBus($tipo["id"]);
        if (empty($asientosOld)) {
            return null;
        }

        $firstAsientoId = null;
        $inserted = 0;

        foreach ($asientosOld as $asientoOld) {
            $asientoLegacy = (string) $asientoOld["id"];
            if ($this->yaMigrado("asiento", $asientoLegacy)) {
                if (!$firstAsientoId) {
                    $firstAsientoId = $this->getNewId(
                        "asiento",
                        $asientoLegacy,
                    );
                }
                continue;
            }

            $data = $this->mapeador->asiento($asientoOld, $busId);
            $this->newConn->executeStatement(
                "INSERT INTO asiento (id, numero, clase, fila, columna, bus_id) VALUES (:id, :numero, :clase, :fila, :columna, :bus_id)",
                $data,
            );

            if (!$firstAsientoId) {
                $firstAsientoId = (int) $data["id"];
            }
            $inserted++;
        }

        $contadores["asiento"] += $inserted;

        return $firstAsientoId;
    }

    private function migrarCliente(array $boletoOld, array &$contadores): ?int
    {
        $clienteId =
            $boletoOld["cliente_boleto"] ??
            ($boletoOld["cliente_documento"] ?? null);
        if (!$clienteId) {
            return null;
        }

        $legacyId = (string) $clienteId;
        if ($this->yaMigrado("cliente", $legacyId)) {
            return (int) $clienteId;
        }

        $old = $this->fetchCliente($clienteId);
        if (!$old) {
            return null;
        }

        $data = $this->mapeador->cliente($old);
        $this->newConn->executeStatement(
            "INSERT INTO cliente (id, nombre, apellido, nit, email, telefono) VALUES (:id, :nombre, :apellido, :nit, :email, :telefono) ON CONFLICT DO NOTHING",
            $data,
        );
        $contadores["cliente"]++;

        return (int) $data["id"];
    }

    private function migrarUsuario(array $boletoOld, array &$contadores): ?int
    {
        $usuarioIds = [$boletoOld["usuario_creacion_id"]];
        if (
            $boletoOld["usuario_actualizacion_id"] &&
            $boletoOld["usuario_actualizacion_id"] !==
                $boletoOld["usuario_creacion_id"]
        ) {
            $usuarioIds[] = $boletoOld["usuario_actualizacion_id"];
        }

        $firstId = null;
        foreach ($usuarioIds as $idOld) {
            if (!$idOld) {
                continue;
            }
            $legacyId = (string) $idOld;

            if ($this->yaMigrado("usuario", $legacyId)) {
                if (!$firstId) {
                    $firstId = (int) $idOld;
                }
                continue;
            }

            $old = $this->fetchUsuario($idOld);
            if (!$old) {
                continue;
            }

            $data = $this->mapeador->usuario($old);
            $fields = implode(", ", array_keys($data));
            $args = implode(
                ", ",
                array_map(fn($k) => ":{$k}", array_keys($data)),
            );
            $this->newConn->executeStatement(
                "INSERT INTO usuario ({$fields}) VALUES ({$args}) ON CONFLICT DO NOTHING",
                $data,
            );
            $contadores["usuario"]++;

            if (!$firstId) {
                $firstId = (int) $data["id"];
            }
        }

        return $firstId ?? ($boletoOld["usuario_creacion_id"] ?? null);
    }

    private function migrarTrayecto(
        ?string $rutaCodigo,
        array &$contadores,
    ): ?int {
        if (!$rutaCodigo) {
            return null;
        }

        $oldRuta = $this->fetchRuta($rutaCodigo);
        if (!$oldRuta) {
            return null;
        }

        $origenId = $this->migrarEstacions(
            $oldRuta["estacion_origen_id"],
            $contadores,
        );
        $destinoId = $this->migrarEstacions(
            $oldRuta["estacion_destino_id"],
            $contadores,
        );
        if (!$origenId || !$destinoId) {
            return null;
        }

        $trayectoId = $this->findTrayectoPorEnclaves($origenId, $destinoId);
        if (!$trayectoId) {
            $data = $this->mapeador->trayecto(
                $oldRuta,
                $origenId,
                $destinoId,
                true,
            );
            $trayectoId = $this->newConn->fetchOne(
                "INSERT INTO trayecto (origen_id, destino_id, distancia_km, duracion_estimada_minutos, activo,  legacy_id) VALUES (:origen_id, :destino_id, :distancia_km, :duracion_estimada_minutos, :activo,  :legacy_id) RETURNING id",
                $data,
            );
            if (!$trayectoId) {
                return null;
            }
            $contadores["trayecto"]++;
        }

        $this->migrarSubTrayectos(
            $rutaCodigo,
            (int) $trayectoId,
            $origenId,
            $destinoId,
            $contadores,
        );

        return (int) $trayectoId;
    }

    private function findTrayectoPorEnclaves(
        int $origenId,
        int $destinoId,
    ): ?int {
        $trayectoId = $this->newConn->fetchOne(
            "SELECT id FROM trayecto WHERE origen_id = :origen AND destino_id = :destino",
            ["origen" => $origenId, "destino" => $destinoId],
        );

        return $trayectoId !== false ? (int) $trayectoId : null;
    }

    private function migrarSubTrayectos(
        string $rutaCodigo,
        int $trayectoPadreId,
        int $rutaOrigenId,
        int $rutaDestinoId,
        array &$contadores,
    ): void {
        $items = $this->fetchRutaEstacionItems($rutaCodigo);
        if (empty($items)) {
            return;
        }

        $stationIds = [$rutaOrigenId];
        foreach ($items as $item) {
            $estId = $this->migrarEstacions($item["estacion_id"], $contadores);
            if ($estId) {
                $stationIds[] = $estId;
            }
        }
        $stationIds[] = $rutaDestinoId;

        for ($i = 0; $i < count($stationIds) - 1; $i++) {
            $origenId = $stationIds[$i];
            $destinoId = $stationIds[$i + 1];
            if ($origenId === $destinoId) {
                continue;
            }

            $subId = $this->findTrayectoPorEnclaves($origenId, $destinoId);
            if (!$subId) {
                $subId = $this->newConn->fetchOne(
                    "INSERT INTO trayecto (origen_id, destino_id, activo) VALUES (:origen_id, :destino_id, true) RETURNING id",
                    [
                        "origen_id" => $origenId,
                        "destino_id" => $destinoId,
                    ],
                );
                if (!$subId) {
                    continue;
                }
                $contadores["trayecto"]++;
            }

            $this->linkTrayectoHijo($trayectoPadreId, (int) $subId);
        }
    }

    private function linkTrayectoHijo(int $padreId, int $hijoId): void
    {
        $exists = $this->newConn->fetchOne(
            "SELECT id FROM subtrayecto WHERE below_to_id = :padre AND trayecto_id = :hijo",
            ["padre" => $padreId, "hijo" => $hijoId],
        );
        if ($exists) {
            return;
        }

        $this->newConn->executeStatement(
            "INSERT INTO subtrayecto (trayecto_id, below_to_id, activo) VALUES (:hijo, :padre, true)",
            ["hijo" => $hijoId, "padre" => $padreId],
        );
    }

    // ─── Salida-driven migration helpers ───────────────────────────

    private function fetchSalidas(
        int $salidas,
        ?string $desde = null,
        ?string $hasta = null,
    ): array {
        $sql = "SELECT TOP $salidas s.*, i.ruta_codigo, i.tipo_bus_id AS it_tipo_bus_id, i.empresa_id AS it_empresa_id
             FROM salida s
             LEFT JOIN itineario i ON i.id = s.itinerario_id
             WHERE s.estado_id in (1,2)";
        $params = [];
        if ($desde) {
            $sql .= " AND s.fecha >= :desde";
            $params["desde"] = $desde;
        }
        if ($hasta) {
            $sql .= " AND s.fecha <= :hasta";
            $params["hasta"] = $hasta;
        }
        $sql .= " ORDER BY s.fecha DESC";

        return $this->fetchOld($sql, $params);
    }

    private function fetchSalidasVentana(
        int $cantidad,
        int $offset,
        ?string $desde = null,
        ?string $hasta = null,
    ): array {
        $sql = "SELECT s.*, i.ruta_codigo, i.tipo_bus_id AS it_tipo_bus_id, i.empresa_id AS it_empresa_id
             FROM salida s
             LEFT JOIN itineario i ON i.id = s.itinerario_id
             WHERE s.estado_id in (1,2)";
        $params = [];
        if ($desde) {
            $sql .= " AND s.fecha >= :desde";
            $params["desde"] = $desde;
        }
        if ($hasta) {
            $sql .= " AND s.fecha <= :hasta";
            $params["hasta"] = $hasta;
        }
        $sql .= " ORDER BY s.fecha DESC OFFSET {$offset} ROWS FETCH NEXT {$cantidad} ROWS ONLY";

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
        // Set de legacy_id ya migrados en el nuevo recorrido (evita N+1).
        $migradas = [];
        $rows = $this->newConn
            ->executeQuery(
                "SELECT legacy_id FROM recorrido WHERE legacy_id IS NOT NULL",
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
        $sql = "SELECT COUNT(*) FROM salida s WHERE s.estado_id IN (1,2)";
        $params = [];
        if ($desde) {
            $sql .= " AND s.fecha >= :desde";
            $params["desde"] = $desde;
        }
        if ($hasta) {
            $sql .= " AND s.fecha <= :hasta";
            $params["hasta"] = $hasta;
        }
        $stmt = $this->oldPdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function crearSalida(
        array $salida,
        ?int $busId,
        ?int $empresaId,
        ?int $trayectoId,
        array &$contadores,
    ): ?int {
        // Nota: el piloto de la salida legacy ya no se migra a nivel de
        // Recorrido — la asignación de piloto/copiloto vive en Bus (ver
        // ADR-013 y siguientes). El piloto histórico de esta salida puntual
        // (que podía diferir del piloto habitual del bus) no tiene destino
        // en el modelo nuevo y se descarta.
        $data = $this->mapeador->recorrido(
            $salida,
            $busId,
            $empresaId,
            $trayectoId,
        );
        $id = $this->newConn->fetchOne(
            "INSERT INTO recorrido (fecha, bus_id, empresa_id, trayecto_id, estado, legacy_id) VALUES (:fecha, :bus_id, :empresa_id, :trayecto_id, :estado, :legacy_id) RETURNING id",
            $data,
        );
        $contadores["salida"]++;

        return (int) $id;
    }

    private function migrarBoletosDeSalida(
        int $salidaId,
        int $nuevaSalidaId,
        ?int $trayectoId,
        ?int $busId,
        array &$contadores,
    ): void {
        $boletos = $this->fetchBoletosPorSalida($salidaId);
        if (empty($boletos)) {
            return;
        }

        foreach ($boletos as $boletoOld) {
            $boletoLegacy = (string) $boletoOld["id"];
            if ($this->yaMigrado("boleto_asiento", $boletoLegacy)) {
                continue;
            }

            $usuarioId = $this->migrarUsuario($boletoOld, $contadores);

            $clienteId = $this->migrarCliente($boletoOld, $contadores);
            if (!$clienteId) {
                $dummyId =
                    $boletoOld["cliente_boleto"] ??
                    ($boletoOld["cliente_documento"] ?? null);
                if ($dummyId) {
                    $this->newConn->executeStatement(
                        "INSERT INTO cliente (id, nombre, apellido) VALUES (:id, :nombre, :apellido) ON CONFLICT DO NOTHING",
                        [
                            "id" => (int) $dummyId,
                            "nombre" => "Cliente",
                            "apellido" => "Migrado",
                        ],
                    );
                    $clienteId = (int) $dummyId;
                    $contadores["cliente"]++;
                }
            }
            if (!$clienteId) {
                continue;
            }

            $asientoId = null;
            if (!empty($boletoOld["asiento_bus_id"])) {
                $asientoId = $this->getNewId(
                    "asiento",
                    (string) $boletoOld["asiento_bus_id"],
                );
            }
            if (!$asientoId && $busId) {
                $asientoId = $this->newConn->fetchOne(
                    "SELECT id FROM asiento WHERE bus_id = :busId ORDER BY id LIMIT 1",
                    ["busId" => $busId],
                );
                $asientoId = $asientoId ? (int) $asientoId : null;
            }
            if (!$asientoId) {
                continue;
            }

            $trayectoBoletoId = $this->resolverTrayectoBoleto(
                $trayectoId,
                $boletoOld,
            );
            if (!$trayectoBoletoId) {
                continue;
            }

            $estado = $this->resolverEstadoBoletoAsiento($boletoOld);

            $ventaId = $this->crearBoletoVenta($usuarioId, $contadores);
            if (!$ventaId) {
                continue;
            }

            $data = $this->mapeador->boletoAsiento(
                $boletoOld,
                $nuevaSalidaId,
                $asientoId,
                $clienteId,
                $trayectoBoletoId,
                $estado,
                $ventaId,
            );
            $this->newConn->executeStatement(
                'INSERT INTO boleto_asiento (recorrido_id, asiento_id, cliente_id, trayecto_id, estado, boleto_venta_id, precio_monto, precio_moneda, legacy_id)
                 VALUES (:recorrido_id, :asiento_id, :cliente_id, :trayecto_id, :estado, :boleto_venta_id, :precio_monto, :precio_moneda, :legacy_id)',
                $data,
            );
            $contadores["boleto_asiento"]++;
        }
    }

    private function crearBoletoVenta(?int $usuarioId, array &$contadores): ?int
    {
        if (!$usuarioId) {
            $usuarioId = 1;
        }

        $insertId = $this->newConn->fetchOne(
            "INSERT INTO boleto_venta (usuario_id) VALUES (:usuario_id) RETURNING id",
            ["usuario_id" => $usuarioId],
        );
        $contadores["boleto_venta"]++;

        return (int) $insertId;
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
     * Indica el trayecto del boleto: el trayecto de la salida o, si el boleto
     * referencia un origen que coincide con un subtrayecto del mismo, ese subtrayecto.
     */
    private function resolverTrayectoBoleto(
        ?int $trayectoId,
        array $boletoOld,
    ): ?int {
        if (!$trayectoId) {
            return null;
        }

        $origenLegacy = $boletoOld["estacion_origen_id"] ?? null;
        if (!$origenLegacy) {
            return $trayectoId;
        }
        $origenId = $this->getNewId("estacion", (string) $origenLegacy) ?: null;
        if (!$origenId) {
            return $trayectoId;
        }

        $subTrayectoId = $this->newConn->fetchOne(
            'SELECT s.trayecto_id FROM subtrayecto s
             JOIN trayecto t ON t.id = s.trayecto_id
             WHERE s.below_to_id = :parent AND t.origen_id = :origen
             ORDER BY s.position NULLS LAST, s.id LIMIT 1',
            ["parent" => $trayectoId, "origen" => $origenId],
        );

        return $subTrayectoId ? (int) $subTrayectoId : $trayectoId;
    }

    private function fetchBoletosPorSalida(int $salidaId): array
    {
        return $this->fetchOld(
            "SELECT * FROM boleto WHERE salida_id = :salidaId ORDER BY id",
            ["salidaId" => $salidaId],
        );
    }

    private function contadoresIniciales(): array
    {
        return [
            "empresa" => 0,
            "estacion" => 0,
            "bus" => 0,
            "asiento" => 0,
            "cliente" => 0,
            "trayecto" => 0,
            "salida" => 0,
            "boleto_asiento" => 0,
            "boleto_venta" => 0,
            "usuario" => 0,
        ];
    }
}
