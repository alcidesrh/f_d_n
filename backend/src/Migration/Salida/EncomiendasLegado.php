<?php

declare(strict_types=1);

namespace App\Migration\Salida;

use App\Entity\Enum\EstadoEncomienda;
use App\Entity\Enum\ModalidadEncomienda;
use App\Entity\Enum\OperacionEncomienda;
use App\Migration\Mapeador;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Encomiendas del legado que viajaron en una salida (ADR-035), con su árbol:
 * empresa, estaciones, ruta, remitente, destinatario, quien paga y quien la
 * retiró, usuario y estación que la recibió, tipo de pago, moneda, especial
 * del catálogo, factura certificada, el boleto con que viaja (si está
 * migrado), su artículo y los eventos de su bitácora.
 *
 * Se llama dentro de la transacción de la salida, después de sus boletos.
 * Una encomienda conserva el id del legado: es el número de su código de
 * barras, el que llevan las guías ya pegadas en los bultos. La identidad no
 * se reajusta (las del legado van por los 10 millones; las nuevas, desde 1).
 *
 * Es idempotente: una ya migrada se completa (estado, bodega, salida,
 * entrega, factura del cobro, eventos de la bitácora que falten). Una que
 * viajó en varias salidas (transbordo) queda con la última ya migrada.
 */
class EncomiendasLegado
{
    /** @var array<string, int> especial del legado → id */
    private array $especiales = [];

    /** @var array<int, ?string> enclave → nombre */
    private array $nombres = [];

    /** @var array<int, ?string> salida → texto de la bitácora */
    private array $salidasTexto = [];

    /** @var (\Closure(array<string, mixed>): bool)|null encomiendas del legado que no se traen (`excluir`) */
    private ?\Closure $excluir = null;

    /** Columnas de una encomienda del legado con su factura y la de su boleto. */
    private const SELECT = "SELECT e.*, b.factura_generada_id AS boleto_fg,
                    fg.sAutorizacionUUIDsat AS fg_uuid, fg.sNumeroDTEsat AS fg_dte, fg.sSerieDTEsat AS fg_serie,
                    CONVERT(varchar(19), fg.fecha, 120) AS fg_fecha,
                    CONVERT(varchar(19), fg.sFechaCertificaDTEsat, 120) AS fg_certificada,
                    fg.importeTotal AS fg_total, fl.empresa_id AS fg_empresa_id,
                    CONVERT(varchar(19), e.fecha_creacion, 120) AS fecha_creacion
               FROM encomienda e
               LEFT JOIN boleto b ON b.id = e.boleto_id
               LEFT JOIN factura_generada fg ON fg.id = e.factura_generada_id
               LEFT JOIN factura fl ON fl.id = fg.factura_id";

    public function __construct(
        private readonly Connection $db,
        private readonly LectorLegado $legado,
        private readonly DependenciasLegado $dependencias,
        private readonly RutasLegado $rutas,
        private readonly Mapeador $mapeador,
    ) {}

    /**
     * @param array<string, int>                   $contadores
     * @param \Closure(string, string): void       $omitir motivo, ejemplo
     */
    public function migrarDeSalida(int $salidaLegado, int $salidaId, ?int $empresaSalida, array &$contadores, \Closure $omitir): void
    {
        $filas = $this->legado->filas(
            self::SELECT . " WHERE e.primera_salida_id = :s
                 OR e.id IN (SELECT encomienda_id FROM encomienda_bitacora WHERE salida_id = :s2)
              ORDER BY e.id",
            ["s" => $salidaLegado, "s2" => $salidaLegado],
        );
        $this->migrarFilas($filas, [$salidaLegado => $salidaId], $empresaSalida, $contadores, $omitir);
    }

    /**
     * Encomiendas del legado por id, viajen o no en una salida (las que
     * cambiaron en bodega, para la sincronización, ADR-042). Fuera de la
     * transacción de una salida: quien llama abre la suya.
     *
     * @param list<int>                      $ids
     * @param array<string, int>             $contadores
     * @param \Closure(string, string): void $omitir motivo, ejemplo
     */
    public function migrarPorIds(array $ids, array &$contadores, \Closure $omitir): void
    {
        if ($ids === []) {
            return;
        }
        $filas = $this->legado->filas(
            self::SELECT . " WHERE e.id IN (" . implode(",", array_map("intval", $ids)) . ") ORDER BY e.id",
        );
        $this->migrarFilas($filas, [], null, $contadores, $omitir);
    }

    /**
     * Encomiendas del legado que no se traen (la sincronización excluye las
     * que escribió el sistema nuevo y aquí no están, ADR-042).
     *
     * @param (\Closure(array<string, mixed>): bool)|null $filtro fila de `encomienda` → true si se omite
     */
    public function excluir(?\Closure $filtro): void
    {
        $this->excluir = $filtro;
    }

    /**
     * @param list<array<string, mixed>>     $filas
     * @param array<int, int>                $conocidas salida del legado → id (la que se está migrando)
     * @param array<string, int>             $contadores
     * @param \Closure(string, string): void $omitir
     */
    private function migrarFilas(array $filas, array $conocidas, ?int $empresaSalida, array &$contadores, \Closure $omitir): void
    {
        if ($filas === []) {
            return;
        }

        $ids = array_map(static fn(array $f) => (int) $f["id"], $filas);
        $eventos = $this->eventos($ids);
        $existentes = [];
        foreach ($this->db->fetchAllAssociative(
            "SELECT id, legacy_id, estado, salida_id, factura_id FROM encomienda WHERE legacy_id IN (:ids)",
            ["ids" => array_map("strval", $ids)],
            ["ids" => ArrayParameterType::STRING],
        ) as $fila) {
            $existentes[(string) $fila["legacy_id"]] = $fila;
        }
        $this->dependencias->precargarClientes([
            ...array_column($filas, "cliente_remitente"),
            ...array_column($filas, "cliente_destinatario"),
            ...array_column($filas, "cliente_documento"),
            ...array_column(array_merge(...array_values($eventos) ?: [[]]), "cliente_id"),
        ]);
        $salidas = $this->salidasMigradas(array_merge(array_keys($conocidas), ...array_map(
            static fn(array $f) => EncomiendaLegada::salidas($f, $eventos[(int) $f["id"]] ?? []),
            $filas,
        )));
        $salidas = $conocidas + $salidas;

        foreach ($filas as $old) {
            $lid = (string) $old["id"];
            if (!isset($existentes[$lid]) && $this->excluir !== null && ($this->excluir)($old)) {
                continue;
            }
            $this->migrarUna($old, EncomiendaLegada::ordenar($eventos[(int) $lid] ?? []), $existentes[$lid] ?? null, $salidas, $empresaSalida, $contadores, $omitir);
        }
    }

    /** Tras revertir la transacción de una salida. */
    public function olvidar(): void
    {
        $this->especiales = [];
        $this->nombres = [];
        $this->salidasTexto = [];
    }

    /**
     * @param list<array<string, mixed>> $eventos ordenados
     * @param array<string, mixed>|null  $existente
     * @param array<int, int>            $salidas salida del legado → id
     * @param array<string, int>         $contadores
     */
    private function migrarUna(array $old, array $eventos, ?array $existente, array $salidas, ?int $empresaSalida, array &$contadores, \Closure $omitir): void
    {
        $lid = (string) $old["id"];
        $empresa = $this->dependencias->empresa($old["empresa_id"] ?? null) ?? $empresaSalida;
        $origen = $this->dependencias->lugar($old["estacion_origen_id"] ?? null);
        $destino = $this->dependencias->lugar($old["estacion_destino_id"] ?? null);
        $remitente = $this->dependencias->cliente($old["cliente_remitente"] ?? null);
        $destinatario = $this->dependencias->cliente($old["cliente_destinatario"] ?? null) ?? $remitente;
        if ($empresa === null || $origen === null || $destino === null) {
            $omitir("encomienda_sin_empresa_o_estacion", "encomienda {$lid}");

            return;
        }
        if ($remitente === null) {
            $omitir("encomienda_sin_cliente", "encomienda {$lid}");

            return;
        }

        $boleto = empty($old["boleto_id"]) ? false : $this->db->fetchAssociative(
            "SELECT id, boleto_venta_id FROM boleto_asiento WHERE legacy_id = :lid",
            ["lid" => (string) $old["boleto_id"]],
        );
        $modalidad = EncomiendaLegada::modalidad($old, $boleto !== false);
        $estado = EncomiendaLegada::estado($old, $eventos);
        $entrega = $estado === EstadoEncomienda::ENTREGADA ? EncomiendaLegada::ultimo($eventos, 5) : null;
        $anulacion = $estado === EstadoEncomienda::ANULADA ? EncomiendaLegada::ultimo($eventos, 6) : null;
        $salidaId = null;
        foreach (array_reverse(EncomiendaLegada::salidas($old, $eventos)) as $s) {
            if (isset($salidas[$s])) {
                $salidaId = $salidas[$s];
                break;
            }
        }
        $clienteFactura = $this->dependencias->cliente($old["cliente_documento"] ?? null)
            ?? ($modalidad === ModalidadEncomienda::POR_COBRAR ? null : $remitente);
        $motivo = $estado === EstadoEncomienda::ANULADA
            ? (EncomiendaLegada::texto($old["observacion"] ?? null) ?? "Anulada en el sistema anterior")
            : null;

        $datos = [
            "modalidad" => $modalidad->value,
            "estado" => $estado->value,
            "ubicacion_id" => $this->dependencias->lugar(EncomiendaLegada::ubicacion($estado, $old, $eventos)),
            "salida_id" => $salidaId,
            "boleto_venta_id" => $modalidad === ModalidadEncomienda::CON_BOLETO ? (int) $boleto["boleto_venta_id"] : null,
            "boleto_id" => $modalidad === ModalidadEncomienda::CON_BOLETO ? (int) $boleto["id"] : null,
            "cliente_factura_id" => $clienteFactura,
            "tipo_pago_id" => $this->dependencias->tipoPago($old["tipo_pago_id"] ?? null),
            "moneda_id" => $this->dependencias->moneda($old["moneda_id"] ?? null),
            "receptor_id" => $this->dependencias->cliente($entrega["cliente_id"] ?? null),
            "entregada_en" => $entrega["fecha"] ?? null,
            "motivo_anulacion" => $motivo,
        ];

        if ($existente === null) {
            $articulo = EncomiendaLegada::articulo($old);
            $total = $modalidad === ModalidadEncomienda::INTERNA ? 0 : EncomiendaLegada::precio($old);
            $datos += [
                "empresa_id" => $empresa,
                "origen_id" => $origen,
                "destino_id" => $destino,
                "ruta_id" => $this->rutas->deCodigo($old["ruta_codigo"] ?? null)?->id,
                "remitente_id" => $remitente,
                "destinatario_id" => $destinatario,
                "total_monto" => $total,
                "total_moneda" => "GTQ",
                "descuento_monto" => 0,
                "descuento_moneda" => "GTQ",
                "estado_facturacion" => "no_aplica",
                "usuario_id" => $this->dependencias->usuario($old["usuario_creacion_id"] ?? null),
                "estacion_id" => $this->dependencias->estacion($old["estacion_creacion_id"] ?? null),
                "observacion" => $estado === EstadoEncomienda::ANULADA ? null : EncomiendaLegada::texto($old["observacion"] ?? null),
                "creada" => $old["fecha_creacion"],
                "legacy_id" => $lid,
            ];
            // El id del legado es el de su código de barras; si ya lo usa otra (creada aquí), uno nuevo.
            if ($this->db->fetchOne("SELECT 1 FROM encomienda WHERE id = :id", ["id" => (int) $lid]) === false) {
                $datos["id"] = (int) $lid;
            } else {
                $omitir("encomienda_id_ocupado", "encomienda {$lid}: se le da otro id, su guía no escanea");
            }
            $id = $this->insertar("encomienda", $datos);
            $this->insertarArticulo($id, $articulo, $contadores);
            $facturaActual = null;
            $contadores["encomienda"] = ($contadores["encomienda"] ?? 0) + 1;
        } else {
            $id = (int) $existente["id"];
            $facturaActual = $existente["factura_id"] === null ? null : (int) $existente["factura_id"];
            $sets = implode(", ", array_map(static fn($k) => "{$k} = :{$k}", array_keys($datos)));
            $this->db->executeStatement("UPDATE encomienda SET {$sets} WHERE id = :id", $datos + ["id" => $id]);
            if ($existente["estado"] !== $estado->value || (int) $existente["salida_id"] !== (int) $salidaId) {
                $contadores["encomienda_actualizada"] = ($contadores["encomienda_actualizada"] ?? 0) + 1;
            }
        }

        $this->factura($id, $old, $modalidad, $facturaActual, $empresa, $clienteFactura ?? $remitente, $anulacion["fecha"] ?? null, $motivo, $contadores);
        $this->bitacora($id, $old, $eventos, $modalidad, $origen, $destino, $contadores);
    }

    /**
     * Factura certificada del legado (la de recibirla si es pagada, la del
     * cobro si es por cobrar). El modelo nuevo tiene una factura por
     * encomienda: si en el legado una factura cubrió varias, cada una lleva
     * su copia (mismo DTE). Anulada la encomienda, su factura también. Sin
     * factura certificada no queda pendiente: el cron certificaría envíos viejos.
     *
     * @param array<string, int> $contadores
     */
    private function factura(int $id, array $old, ModalidadEncomienda $modalidad, ?int $actual, int $empresa, int $receptor, ?string $anuladaEn, ?string $motivo, array &$contadores): void
    {
        $facturaId = $actual;
        if ($facturaId === null && $modalidad !== ModalidadEncomienda::CON_BOLETO && Mapeador::facturaCertificada($old)) {
            $emisor = $this->dependencias->empresa($old["fg_empresa_id"] ?? null) ?? $empresa;
            $datosEmisor = $this->db->fetchAssociative("SELECT nit, nombre, nombre_comercial FROM empresa WHERE id = :id", ["id" => $emisor]);
            $data = $this->mapeador->factura(
                $old,
                $datosEmisor ?: ["nit" => null, "nombre" => "Sin emisor", "nombre_comercial" => null],
                $this->dependencias->datosCliente($receptor),
            );
            if ($data !== null) {
                $facturaId = $this->insertar("factura", $data);
                $contadores["factura"] = ($contadores["factura"] ?? 0) + 1;
            }
        }
        if ($facturaId === null) {
            return;
        }
        $anulada = $motivo !== null;
        if ($anulada) {
            $this->db->executeStatement(
                "UPDATE factura SET anulada_en = COALESCE(anulada_en, :en), motivo_anulacion = COALESCE(motivo_anulacion, :motivo) WHERE id = :id",
                ["en" => $anuladaEn ?? $old["fecha_creacion"], "motivo" => mb_substr($motivo, 0, 255), "id" => $facturaId],
            );
        }
        $this->db->executeStatement(
            "UPDATE encomienda SET factura_id = :f, estado_facturacion = :ef WHERE id = :id",
            ["f" => $facturaId, "ef" => $anulada ? "anulada" : "certificada", "id" => $id],
        );
    }

    /** @param array<string, int> $contadores */
    private function insertarArticulo(int $encomienda, array $a, array &$contadores): void
    {
        $this->insertar("encomienda_articulo", [
            "encomienda_id" => $encomienda,
            "tipo" => $a["tipo"]->value,
            "especial_id" => $a["especial"] === null ? null : $this->especial($a["especial"], $a["tarifa"], $contadores),
            "descripcion" => $a["descripcion"],
            "cantidad" => $a["cantidad"],
            "peso" => $a["peso"],
            "alto" => $a["alto"],
            "ancho" => $a["ancho"],
            "largo" => $a["largo"],
            "tarifa_monto" => $a["tarifa"],
            "tarifa_moneda" => "GTQ",
            "recargo_monto" => 0,
            "recargo_moneda" => "GTQ",
            "importe_monto" => $a["importe"],
            "importe_moneda" => "GTQ",
        ]);
    }

    /**
     * Especial del catálogo por su id del legado (`app:encomienda:legado` ya
     * los copia; si falta, se crea con el precio de esta encomienda).
     *
     * @param array<string, int> $contadores
     */
    private function especial(int $legacyId, int $precio, array &$contadores): ?int
    {
        $clave = (string) $legacyId;
        if (isset($this->especiales[$clave])) {
            return $this->especiales[$clave];
        }
        $id = $this->db->fetchOne("SELECT id FROM encomienda_especial WHERE legacy_id = :l", ["l" => $clave]);
        if ($id === false) {
            $old = $this->legado->fila("SELECT * FROM encomienda_especiales_tipo WHERE id = :id", ["id" => $legacyId]);
            if ($old === null) {
                return null;
            }
            $id = $this->insertar("encomienda_especial", [
                "nombre" => EncomiendaLegada::texto($old["nombre"] ?? null, 100) ?? "Especial {$legacyId}",
                "descripcion" => EncomiendaLegada::texto($old["descripcion"] ?? null),
                "precio_monto" => $precio,
                "precio_moneda" => "GTQ",
                "permite_por_cobrar" => filter_var($old["permitePorCobrar"] ?? true, FILTER_VALIDATE_BOOL) ? "true" : "false",
                "activo" => filter_var($old["activo"] ?? true, FILTER_VALIDATE_BOOL) ? "true" : "false",
                "legacy_id" => $clave,
            ]);
            $contadores["encomienda_especial"] = ($contadores["encomienda_especial"] ?? 0) + 1;
        }

        return $this->especiales[$clave] = (int) $id;
    }

    /**
     * Eventos de la bitácora del legado que todavía no están en la nuestra
     * (se reconocen por `detalle.legado`), con el usuario y la fecha del
     * legado y el mismo detalle que anota `RegistradorBitacora`.
     *
     * @param list<array<string, mixed>> $eventos ordenados
     * @param array<string, int>         $contadores
     */
    private function bitacora(int $id, array $old, array $eventos, ModalidadEncomienda $modalidad, int $origen, int $destino, array &$contadores): void
    {
        $hechos = array_flip($this->db->fetchFirstColumn(
            "SELECT detalle->>'legado' FROM bitacora WHERE entidad = 'encomienda' AND registro_id = :id AND detalle->>'legado' IS NOT NULL",
            ["id" => $id],
        ));
        foreach ($eventos as $e) {
            $estado = EncomiendaLegada::estadoDe($e["estado_id"]);
            if ($estado === null || isset($hechos[(string) $e["id"]])) {
                continue;
            }
            $estacion = $this->nombre($this->dependencias->lugar($e["estacion"] ?? null));
            $detalle = match ($estado) {
                EstadoEncomienda::RECIBIDA => [
                    "modalidad" => $modalidad->value,
                    "origen" => $this->nombre($origen),
                    "destino" => $this->nombre($destino),
                    "bultos" => max(1, (int) ($old["cantidad"] ?? 1)),
                    "total" => $modalidad === ModalidadEncomienda::INTERNA ? 0 : EncomiendaLegada::precio($old),
                    "estacion" => $estacion,
                ],
                EstadoEncomienda::ENTREGADA => [
                    "estacion" => $estacion,
                    "retira" => $this->nombreCliente($this->dependencias->cliente($e["cliente_id"] ?? null)),
                ],
                EstadoEncomienda::ANULADA => [
                    "estacion" => $estacion,
                    "motivo" => EncomiendaLegada::texto($old["observacion"] ?? null),
                ],
                default => [
                    "estacion" => $estacion,
                    "salida" => $this->salidaTexto($e["salida_id"] ?? null),
                ],
            };
            $usuario = $this->dependencias->usuario($e["usuario_id"] ?? null);
            $this->db->insert("bitacora", [
                "entidad" => "encomienda",
                "registro_id" => $id,
                "operacion" => OperacionEncomienda::deEstado($estado)->value,
                "usuario_id" => $usuario,
                "usuario_nombre" => $usuario === null ? null : $this->db->fetchOne(
                    "SELECT LEFT(COALESCE(NULLIF(TRIM(CONCAT(nombre, ' ', apellido)), ''), username), 100) FROM usuario WHERE id = :id",
                    ["id" => $usuario],
                ),
                "fecha" => $e["fecha"],
                "detalle" => json_encode(
                    array_filter($detalle, static fn($v) => $v !== null) + ["legado" => (string) $e["id"]],
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                ),
            ]);
            $contadores["encomienda_bitacora"] = ($contadores["encomienda_bitacora"] ?? 0) + 1;
        }
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, list<array<string, mixed>>> encomienda del legado → eventos
     */
    private function eventos(array $ids): array
    {
        $porEncomienda = [];
        foreach (array_chunk($ids, 500) as $lote) {
            foreach ($this->legado->filas(
                "SELECT id, encomienda_id, estacion, estado_id, usuario_id, salida_id, cliente_id,
                        CONVERT(varchar(19), fecha, 120) AS fecha
                   FROM encomienda_bitacora WHERE encomienda_id IN (" . implode(",", array_map("intval", $lote)) . ")",
            ) as $e) {
                $porEncomienda[(int) $e["encomienda_id"]][] = $e;
            }
        }

        return $porEncomienda;
    }

    /**
     * @param list<int> $legado
     *
     * @return array<int, int> salida del legado → id (solo las ya migradas)
     */
    private function salidasMigradas(array $legado): array
    {
        $legado = array_values(array_unique($legado));
        if ($legado === []) {
            return [];
        }
        $ids = [];
        foreach ($this->db->fetchAllNumeric(
            "SELECT legacy_id, id FROM salida WHERE legacy_id IN (:ids)",
            ["ids" => array_map("strval", $legado)],
            ["ids" => ArrayParameterType::STRING],
        ) as [$lid, $id]) {
            $ids[(int) $lid] = (int) $id;
        }

        return $ids;
    }

    /** Como la anota `RegistradorBitacora`: «id · fecha · origen → destino (bus X)». */
    private function salidaTexto(mixed $legacyId): ?string
    {
        if (!is_numeric($legacyId)) {
            return null;
        }
        if (!array_key_exists((int) $legacyId, $this->salidasTexto)) {
            $s = $this->db->fetchAssociative(
                "SELECT s.id, TO_CHAR(s.fecha, 'DD/MM/YYYY HH24:MI') AS fecha, o.nombre AS origen, d.nombre AS destino, b.codigo AS bus
                   FROM salida s
                   JOIN ruta r ON r.id = s.ruta_id
                   LEFT JOIN parada po ON po.ruta_id = r.id AND po.orden = 0
                   LEFT JOIN enclave o ON o.id = po.enclave_id
                   LEFT JOIN LATERAL (SELECT enclave_id FROM parada WHERE ruta_id = r.id ORDER BY orden DESC LIMIT 1) pd ON true
                   LEFT JOIN enclave d ON d.id = pd.enclave_id
                   LEFT JOIN bus b ON b.id = s.bus_id
                  WHERE s.legacy_id = :lid",
                ["lid" => (string) $legacyId],
            );
            $this->salidasTexto[(int) $legacyId] = $s === false ? null : sprintf(
                "%d · %s · %s → %s%s",
                $s["id"],
                $s["fecha"],
                $s["origen"],
                $s["destino"],
                $s["bus"] !== null ? " (bus {$s["bus"]})" : "",
            );
        }

        return $this->salidasTexto[(int) $legacyId];
    }

    private function nombre(?int $enclave): ?string
    {
        if ($enclave === null) {
            return null;
        }
        if (!array_key_exists($enclave, $this->nombres)) {
            $nombre = $this->db->fetchOne("SELECT nombre FROM enclave WHERE id = :id", ["id" => $enclave]);
            $this->nombres[$enclave] = $nombre === false ? null : (string) $nombre;
        }

        return $this->nombres[$enclave];
    }

    private function nombreCliente(?int $cliente): ?string
    {
        $nombre = $this->dependencias->datosCliente($cliente)["nombre"] ?? null;

        return $nombre === null || $nombre === "" ? null : (string) $nombre;
    }

    /** INSERT de una fila; devuelve su id. */
    private function insertar(string $tabla, array $data): int
    {
        $campos = implode(", ", array_keys($data));
        $args = implode(", ", array_map(static fn($k) => ":{$k}", array_keys($data)));

        return (int) $this->db->fetchOne("INSERT INTO {$tabla} ({$campos}) VALUES ({$args}) RETURNING id", $data);
    }
}
