<?php

declare(strict_types=1);

namespace App\PruebaLegado;

use App\Reporte\CuadreVentaBoletos;
use App\Reporte\DetalleFacturaBoletos;
use App\Reporte\FiltroCuadre;
use App\Reporte\FiltroDetalle;
use App\Reporte\LineaBoleto;

/**
 * TEMPORAL (ver `Legado`). Los reportes de venta contra los boletos del sistema anterior: arma
 * las mismas `LineaBoleto` que `ConsultaReportes` y deja el cálculo y el PDF/Excel a las clases
 * del reporte nuevo. Los ids de estación y empresa son los del legado.
 */
final class ReportesLegado
{
    /** `boleto_estado`: 4 anulado, 5 reasignado (no cuenta), 6 cancelado. */
    private const ANULADOS = [4, 6];
    private const TARJETAS = [2, 3, 4];

    private const SQL = "SELECT bo.id, bo.fecha_creacion, bo.estado_id, bo.precioCalculado, bo.tipo_pago_id, bo.identificador_web, bo.cliente_documento,
            bo.voucher_agencia_id, bo.voucher_estacion_id, bo.voucher_internet_id, bo.autorizacion_cortesia_id,
            m.sigla AS moneda, u.username, u.names, u.surnames, ba.numero AS asiento,
            s.id AS salida_id, s.fecha AS salida_fecha, s.bus_codigo, pi.codigo AS piloto, ro.nombre AS ruta_origen, rd.nombre AS ruta_destino,
            eo.nombre AS origen, ed.nombre AS destino,
            fg.id AS factura_id, fg.consecutivo, fg.sNumeroDTEsat AS dte, fg.sSerieDTEsat AS serie_dte, fg.autorizacionTarjeta AS autorizacion,
            fg.referenciaExterna AS referencia, f.serieResolucionFactura AS serie_resolucion
        FROM boleto bo
        JOIN salida s ON s.id = bo.salida_id
        JOIN itineario i ON i.id = s.itinerario_id
        JOIN ruta r ON r.codigo = i.ruta_codigo
        JOIN estacion ro ON ro.id = r.estacion_origen_id
        JOIN estacion rd ON rd.id = r.estacion_destino_id
        LEFT JOIN estacion eo ON eo.id = bo.estacion_origen_id
        LEFT JOIN estacion ed ON ed.id = bo.estacion_destino_id
        LEFT JOIN bus_asiento ba ON ba.id = bo.asiento_bus_id
        LEFT JOIN custom_user u ON u.id = bo.usuario_creacion_id
        LEFT JOIN moneda m ON m.id = bo.moneda_id
        LEFT JOIN piloto pi ON pi.id = s.piloto_id
        LEFT JOIN factura_generada fg ON fg.id = bo.factura_generada_id
        LEFT JOIN factura f ON f.id = fg.factura_id
        WHERE bo.estado_id <> 5";

    public function __construct(private readonly Legado $db) {}

    /** @return array<string, mixed> mismo contrato que `GET /api/reportes/opciones`, sin alcance fijo */
    public function opciones(): array
    {
        return [
            "hoy" => Legado::ahora()->format("Y-m-d"),
            "estaciones" => array_map(static fn(array $e) => ["id" => (int) $e["id"], "nombre" => (string) $e["nombre"], "departamento" => $e["departamento"]], $this->db->filas(
                "SELECT e.id, e.nombre, d.nombre AS departamento FROM estacion e LEFT JOIN departamento d ON d.id = e.departamento_id WHERE e.activo = 1 ORDER BY e.nombre",
            )),
            "empresas" => array_map(static fn(array $e) => ["id" => (int) $e["id"], "nombre" => (string) $e["nombre"]], $this->db->filas(
                "SELECT id, COALESCE(NULLIF(alias, ''), nombre) AS nombre FROM empresa WHERE activo = 1 ORDER BY 2",
            )),
            "monedas" => array_map(static fn(array $m) => ["id" => (int) $m["id"], "sigla" => (string) $m["sigla"], "nombre" => (string) $m["nombre"]], $this->db->filas(
                "SELECT id, sigla, nombre FROM moneda WHERE activo = 1 ORDER BY sigla",
            )),
            "alcance" => ["estacion" => null, "empresa" => null],
        ];
    }

    public function cuadre(FiltroCuadre $f): CuadreVentaBoletos
    {
        $inicio = $f->fecha->format("Y-m-d 00:00:00");
        $fin = $f->fecha->modify("+1 day")->format("Y-m-d 00:00:00");

        $sql = " AND bo.fecha_creacion >= :inicio AND bo.fecha_creacion < :fin AND m.sigla = :moneda";
        $params = ["inicio" => $inicio, "fin" => $fin, "moneda" => $f->moneda];
        if ($f->estacionId !== null) {
            $sql .= " AND bo.estacion_creacion_id = " . $f->estacionId;
        }
        if ($f->empresaId !== null) {
            $sql .= " AND s.empresa_id = " . $f->empresaId;
        }

        $otras = [];
        if ($f->estacionId !== null) {
            $otras = $this->lineas(
                " AND s.fecha >= :inicio AND s.fecha < :fin AND bo.fecha_creacion < :inicio AND bo.estado_id NOT IN (4, 6)
                  AND bo.estacion_creacion_id <> " . $f->estacionId . " AND bo.estacion_origen_id = " . $f->estacionId . " AND m.sigla = :moneda"
                . ($f->empresaId !== null ? " AND s.empresa_id = " . $f->empresaId : ""),
                $params,
            );
        }

        return new CuadreVentaBoletos(
            $f->fecha,
            $this->nombre("SELECT nombre FROM estacion WHERE id = ", $f->estacionId),
            $this->nombre("SELECT COALESCE(NULLIF(alias, ''), nombre) AS nombre FROM empresa WHERE id = ", $f->empresaId),
            $f->moneda,
            $this->lineas($sql, $params),
            $otras,
        );
    }

    public function detalle(FiltroDetalle $f): DetalleFacturaBoletos
    {
        $sql = " AND bo.fecha_creacion >= :inicio AND bo.fecha_creacion < :fin AND bo.estado_id NOT IN (4, 6)";
        $params = ["inicio" => $f->desde->format("Y-m-d 00:00:00"), "fin" => $f->hasta->modify("+1 day")->format("Y-m-d 00:00:00")];
        if ($f->estacionId !== null) {
            $sql .= " AND bo.estacion_creacion_id = " . $f->estacionId;
        }
        if ($f->empresaId !== null) {
            $sql .= " AND s.empresa_id = " . $f->empresaId;
        }
        if ($f->autorizacion !== null) {
            $sql .= " AND fg.autorizacionTarjeta LIKE :autorizacion";
            $params["autorizacion"] = "%" . self::escapar($f->autorizacion) . "%";
        }
        if ($f->soloTarjetas) {
            $sql .= " AND fg.autorizacionTarjeta IS NOT NULL";
        }
        if ($f->referencia !== null) {
            $sql .= " AND fg.referenciaExterna LIKE :referencia";
            $params["referencia"] = "%" . self::escapar($f->referencia) . "%";
        }
        if ($f->soloReferencias) {
            $sql .= " AND fg.referenciaExterna IS NOT NULL";
        }

        return new DetalleFacturaBoletos(
            $f->rotulo(),
            $this->nombre("SELECT nombre FROM estacion WHERE id = ", $f->estacionId),
            $this->nombre("SELECT COALESCE(NULLIF(alias, ''), nombre) AS nombre FROM empresa WHERE id = ", $f->empresaId),
            $this->lineas($sql, $params),
        );
    }

    /**
     * @param array<string, scalar|null> $params
     *
     * @return list<LineaBoleto>
     */
    private function lineas(string $filtro, array $params): array
    {
        $filas = $this->db->filas(self::SQL . $filtro . " ORDER BY bo.fecha_creacion, bo.id", $params);

        // El legado no tiene "venta": los boletos de una misma emisión comparten `identificador_web`
        // (o, sin él, usuario + segundo + salida + cliente). El id de la venta es el menor de sus boletos.
        $ventas = [];
        foreach ($filas as $b) {
            $clave = $b["identificador_web"] ?? implode("|", [$b["username"], substr((string) $b["fecha_creacion"], 0, 19), $b["salida_id"], $b["cliente_documento"]]);
            $ventas[$clave] = min($ventas[$clave] ?? PHP_INT_MAX, (int) $b["id"]);
        }

        return array_map(function (array $b) use ($ventas): LineaBoleto {
            $clave = $b["identificador_web"] ?? implode("|", [$b["username"], substr((string) $b["fecha_creacion"], 0, 19), $b["salida_id"], $b["cliente_documento"]]);
            $sinCobro = ($b["voucher_agencia_id"] ?? $b["voucher_estacion_id"] ?? $b["voucher_internet_id"] ?? $b["autorizacion_cortesia_id"]) !== null;
            $dte = $b["dte"] ?? $b["consecutivo"];

            return new LineaBoleto(
                (int) $b["id"],
                $ventas[$clave],
                new \DateTimeImmutable((string) $b["fecha_creacion"]),
                (string) ($b["username"] ?? "—"),
                trim($b["names"] . " " . $b["surnames"]),
                in_array((int) $b["estado_id"], self::ANULADOS, true),
                (int) round((float) $b["precioCalculado"] * 100),
                (string) ($b["moneda"] ?? "GTQ"),
                $sinCobro,
                (int) $b["salida_id"],
                new \DateTimeImmutable((string) $b["salida_fecha"]),
                $b["bus_codigo"],
                $b["piloto"],
                sprintf("%s - %s", $b["ruta_origen"], $b["ruta_destino"]),
                (string) $b["origen"],
                (string) $b["destino"],
                (int) $b["asiento"],
                $b["factura_id"] === null ? null : (int) $b["factura_id"],
                $b["serie_dte"] ?? $b["serie_resolucion"],
                $dte === null ? null : (int) $dte,
                $b["factura_id"] === null ? "no_aplica" : "certificada",
                in_array((int) $b["tipo_pago_id"], self::TARJETAS, true) || ($b["autorizacion"] ?? "") !== "",
                ($b["autorizacion"] ?? "") !== "" ? (string) $b["autorizacion"] : null,
                ($b["referencia"] ?? "") !== "" ? (string) $b["referencia"] : null,
            );
        }, $filas);
    }

    private function nombre(string $sql, ?int $id): ?string
    {
        return $id === null ? null : $this->db->fila($sql . $id)["nombre"] ?? null;
    }

    private static function escapar(string $texto): string
    {
        return str_replace(["[", "%", "_"], ["[[]", "[%]", "[_]"], $texto);
    }
}
