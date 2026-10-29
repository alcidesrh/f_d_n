<?php

declare(strict_types=1);

namespace App\Tablero;

use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoDepositoAgencia;
use App\Entity\Enum\EstadoSalida;
use App\Programador\RegistroTareas;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Lee de la base lo que necesita el tablero, ya agregado, con DBAL (el
 * filtro de empresa de Doctrine no aplica aquí: el alcance va explícito en
 * cada consulta). Solo lectura. Importes en centavos de la moneda principal.
 *
 * Una venta cuenta si está confirmada; un boleto, si no se anuló ni se
 * reasignó (el reasignado cede su precio al boleto nuevo). Los no
 * presentados (`cancelado`) conservan su importe pero liberan el asiento.
 */
final class ConsultaTablero
{
    public const MONEDA = "GTQ";

    /**
     * Venta sin cobro: cortesía o voucher. Lo vendido por una agencia siempre
     * se cobra (descuenta su saldo): las ventas de agencia migradas traen
     * `voucher` por el `voucher_agencia_id` del legado y no son regalos.
     */
    private const SIN_COBRO = "(v.cortesia OR (v.voucher AND v.canal <> 'agencia'))";

    public function __construct(
        private readonly Connection $db,
        private readonly RegistroTareas $tareas,
    ) {}

    public function tablero(Periodo $p, ?int $empresa): Tablero
    {
        $m = $this->movimientos($p, $empresa);

        return new Tablero(
            $p,
            $m["ventas"],
            $m["incidencias"],
            $this->salidas($p, $empresa),
            $this->sinCerrar($p, $empresa),
            $m["puntos"],
            $this->agencias($p, $empresa),
            $this->facturacion($empresa),
            $this->depositos($empresa),
            $this->flota($p, $empresa),
            $this->boletosHoy($p, $empresa),
            $this->tareas->salud($p->ahora),
        );
    }

    /**
     * Boletos de las salidas de hoy por estado y por quien los vendió, sin la
     * página web (su operación va aparte). Solo ventas confirmadas.
     *
     * @return list<array{usuarioId: ?int, nombre: ?string, usuario: ?string, estado: string, boletos: int}>
     */
    private function boletosHoy(Periodo $p, ?int $empresa): array
    {
        $filas = $this->db->fetchAllAssociative(
            sprintf(
                "SELECT u.id AS usuario_id,
                        COALESCE(NULLIF(TRIM(CONCAT(u.nombre, ' ', u.apellido)), ''), u.username) AS nombre,
                        u.username AS usuario, b.estado, COUNT(*) AS boletos
                   FROM boleto_asiento b
                   JOIN boleto_venta v ON v.id = b.boleto_venta_id
                   JOIN salida s ON s.id = b.salida_id
                   LEFT JOIN usuario u ON u.id = v.usuario_id
                  WHERE v.estado = 'confirmada' AND v.canal <> 'web'
                    AND s.fecha >= :hoy AND s.fecha < :manana %s
                  GROUP BY 1, 2, 3, 4",
                self::deEmpresa($empresa),
            ),
            [
                "hoy" => $p->hoy()->format("Y-m-d H:i:s"),
                "manana" => $p->hoy()->modify("+1 day")->format("Y-m-d H:i:s"),
            ] + ($empresa !== null ? ["empresa" => $empresa] : []),
        );

        return array_map(static fn(array $f) => [
            "usuarioId" => $f["usuario_id"] !== null ? (int) $f["usuario_id"] : null,
            "nombre" => $f["nombre"] !== null ? (string) $f["nombre"] : null,
            "usuario" => $f["usuario"] !== null ? (string) $f["usuario"] : null,
            "estado" => (string) $f["estado"],
            "boletos" => (int) $f["boletos"],
        ], $filas);
    }

    /**
     * @param list<int>|null $ids solo esas (el alcance); null = todas
     *
     * @return list<array{id: int, nombre: string}>
     */
    public function empresas(?array $ids): array
    {
        $filas = $this->db->fetchAllAssociative(
            "SELECT id, COALESCE(NULLIF(alias, ''), nombre) AS nombre FROM empresa" . ($ids !== null ? " WHERE id IN (:ids)" : "") . " ORDER BY 2",
            $ids !== null ? ["ids" => $ids === [] ? [0] : $ids] : [],
            $ids !== null ? ["ids" => \Doctrine\DBAL\ArrayParameterType::INTEGER] : [],
        );

        return array_map(static fn(array $f) => ["id" => (int) $f["id"], "nombre" => (string) $f["nombre"]], $filas);
    }

    /**
     * Una sola pasada por los boletos vendidos de las dos ventanas, por cubo
     * (hora o día), canal y punto de venta; de ahí salen la serie, las
     * incidencias y el ranking de puntos.
     *
     * @return array{ventas: list<array{actual: bool, t: string, canal: string, ingresos: int, boletos: int}>, incidencias: array{actual: array{anulados: int, montoAnulado: int, cortesias: int, valorCortesias: int}, anterior: array{anulados: int, montoAnulado: int, cortesias: int, valorCortesias: int}}, puntos: list<array{tipo: string, nombre: string, ingresos: int, boletos: int, anterior: int}>}
     */
    private function movimientos(Periodo $p, ?int $empresa): array
    {
        $vivo = "b.estado NOT IN ('anulado', 'reasignado')";
        $sinCobro = self::SIN_COBRO;
        $cobrado = "{$vivo} AND NOT " . self::SIN_COBRO;
        $filas = $this->db->fetchAllAssociative(
            sprintf(
                "SELECT v.created_at >= :inicio AS actual,
                        to_char(date_trunc(:unidad, v.created_at), 'YYYY-MM-DD HH24:00') AS t,
                        v.canal,
                        CASE WHEN v.canal = 'web' THEN 'web' WHEN v.agencia_id IS NOT NULL THEN 'agencia' ELSE 'estacion' END AS tipo,
                        CASE WHEN v.canal = 'web' THEN 'Página web' ELSE COALESCE(ag.nombre, en.nombre, 'Sin estación') END AS nombre,
                        COALESCE(SUM(b.precio_monto) FILTER (WHERE {$cobrado}), 0) AS ingresos,
                        COUNT(*) FILTER (WHERE {$cobrado}) AS boletos,
                        COUNT(*) FILTER (WHERE b.estado = 'anulado') AS anulados,
                        COALESCE(SUM(b.precio_monto) FILTER (WHERE b.estado = 'anulado'), 0) AS monto_anulado,
                        COUNT(*) FILTER (WHERE {$vivo} AND {$sinCobro}) AS cortesias,
                        COALESCE(SUM(b.precio_monto) FILTER (WHERE {$vivo} AND {$sinCobro}), 0) AS valor_cortesias
                   FROM boleto_asiento b
                   JOIN boleto_venta v ON v.id = b.boleto_venta_id
                   JOIN salida s ON s.id = b.salida_id
                   LEFT JOIN agencia ag ON ag.id = v.agencia_id
                   LEFT JOIN enclave en ON en.id = v.estacion_id
                  WHERE v.estado = 'confirmada' AND b.precio_moneda = :moneda AND %s %s
                  GROUP BY 1, 2, 3, 4, 5",
                self::ventanas("v.created_at"),
                self::deEmpresa($empresa),
            ),
            $this->parametros($p, $empresa) + ["unidad" => $p->porHora() ? "hour" : "day"],
        );

        $vacio = ["anulados" => 0, "montoAnulado" => 0, "cortesias" => 0, "valorCortesias" => 0];
        $incidencias = ["actual" => $vacio, "anterior" => $vacio];
        $ventas = [];
        $puntos = [];
        foreach ($filas as $f) {
            $actual = (bool) $f["actual"];
            $k = $actual ? "actual" : "anterior";
            $incidencias[$k]["anulados"] += (int) $f["anulados"];
            $incidencias[$k]["montoAnulado"] += (int) $f["monto_anulado"];
            $incidencias[$k]["cortesias"] += (int) $f["cortesias"];
            $incidencias[$k]["valorCortesias"] += (int) $f["valor_cortesias"];
            if ((int) $f["boletos"] === 0) {
                continue;
            }
            $ventas[] = ["actual" => $actual, "t" => (string) $f["t"], "canal" => (string) $f["canal"], "ingresos" => (int) $f["ingresos"], "boletos" => (int) $f["boletos"]];
            $punto = $f["tipo"] . "|" . $f["nombre"];
            $puntos[$punto] ??= ["tipo" => (string) $f["tipo"], "nombre" => (string) $f["nombre"], "ingresos" => 0, "boletos" => 0, "anterior" => 0];
            if ($actual) {
                $puntos[$punto]["ingresos"] += (int) $f["ingresos"];
                $puntos[$punto]["boletos"] += (int) $f["boletos"];
            } else {
                $puntos[$punto]["anterior"] += (int) $f["ingresos"];
            }
        }

        return [
            "ventas" => $ventas,
            "incidencias" => $incidencias,
            "puntos" => array_values(array_filter($puntos, static fn(array $pt) => $pt["boletos"] > 0)),
        ];
    }

    /** @return list<SalidaTablero> */
    private function salidas(Periodo $p, ?int $empresa): array
    {
        $filas = $this->db->fetchAllAssociative(
            sprintf(
                "WITH sal AS (
                    SELECT s.* FROM salida s WHERE s.fecha >= :desde AND s.fecha < :hasta %s
                 ), bol AS (
                    SELECT b.salida_id,
                           SUM(b.orden_baja - b.orden_sube) FILTER (WHERE b.estado NOT IN (:libres)) AS ocupado,
                           COUNT(*) FILTER (WHERE b.estado NOT IN (:libres)) AS boletos,
                           COALESCE(SUM(b.precio_monto) FILTER (WHERE b.estado NOT IN (:libres) AND NOT %s AND b.precio_moneda = :moneda), 0) AS ingresos,
                           COUNT(*) FILTER (WHERE b.estado = :cancelado) AS no_presentados
                      FROM boleto_asiento b
                      JOIN boleto_venta v ON v.id = b.boleto_venta_id AND v.estado = 'confirmada'
                     WHERE b.salida_id IN (SELECT id FROM sal)
                     GROUP BY b.salida_id
                 ), par AS (
                    SELECT sp.salida_id, MAX(sp.orden) AS tramos, MAX(sp.hora) AS llegada
                      FROM salida_parada sp WHERE sp.salida_id IN (SELECT id FROM sal) GROUP BY sp.salida_id
                 ), cap AS (
                    SELECT a.bus_id, COUNT(*) AS asientos FROM asiento a WHERE a.bus_id IN (SELECT bus_id FROM sal) GROUP BY a.bus_id
                 )
                 SELECT sal.id, sal.fecha, sal.estado, sal.ruta_id, r.nombre AS ruta, sal.bus_id, bus.codigo AS bus,
                        COALESCE(NULLIF(e.alias, ''), e.nombre) AS empresa,
                        COALESCE(cap.asientos, 0) AS asientos, COALESCE(par.tramos, 0) AS tramos, par.llegada,
                        COALESCE(bol.ocupado, 0) AS ocupado, COALESCE(bol.boletos, 0) AS boletos,
                        COALESCE(bol.ingresos, 0) AS ingresos, COALESCE(bol.no_presentados, 0) AS no_presentados
                   FROM sal
                   JOIN ruta r ON r.id = sal.ruta_id
                   LEFT JOIN bus ON bus.id = sal.bus_id
                   LEFT JOIN empresa e ON e.id = sal.empresa_id
                   LEFT JOIN cap ON cap.bus_id = sal.bus_id
                   LEFT JOIN par ON par.salida_id = sal.id
                   LEFT JOIN bol ON bol.salida_id = sal.id
                  ORDER BY sal.fecha, sal.id",
                $empresa !== null ? "AND s.empresa_id = :empresa" : "",
                self::SIN_COBRO,
            ),
            [
                "desde" => $p->inicioLectura()->format("Y-m-d H:i:s"),
                "hasta" => $p->hoy()->modify("+8 days")->format("Y-m-d H:i:s"),
                "libres" => EstadoBoletoAsiento::liberanAsiento(),
                "cancelado" => EstadoBoletoAsiento::CANCELADO->value,
                "moneda" => self::MONEDA,
            ] + ($empresa !== null ? ["empresa" => $empresa] : []),
            ["libres" => ArrayParameterType::STRING],
        );

        return array_map(static fn(array $f) => new SalidaTablero(
            (int) $f["id"],
            new \DateTimeImmutable((string) $f["fecha"]),
            (string) $f["estado"],
            (int) $f["ruta_id"],
            (string) $f["ruta"],
            $f["bus"] !== null ? (string) $f["bus"] : null,
            $f["bus_id"] !== null ? (int) $f["bus_id"] : null,
            $f["empresa"] !== null ? (string) $f["empresa"] : null,
            (int) $f["asientos"],
            (int) $f["tramos"],
            $f["llegada"] !== null ? new \DateTimeImmutable((string) $f["llegada"]) : null,
            (int) $f["ocupado"],
            (int) $f["boletos"],
            (int) $f["ingresos"],
            (int) $f["no_presentados"],
        ), $filas);
    }

    /**
     * Salidas que ya llegaron a su última parada (o salieron hace más de un
     * día, si no tienen horario) y siguen sin finalizar.
     *
     * @return list<array{id: int, fecha: string, ruta: string, estado: string, llegada: string}>
     */
    private function sinCerrar(Periodo $p, ?int $empresa): array
    {
        $filas = $this->db->fetchAllAssociative(
            sprintf(
                "SELECT s.id, s.fecha, r.nombre AS ruta, s.estado,
                        COALESCE((SELECT MAX(sp.hora) FROM salida_parada sp WHERE sp.salida_id = s.id), s.fecha + INTERVAL '1 day') AS llegada
                   FROM salida s JOIN ruta r ON r.id = s.ruta_id
                  WHERE s.estado IN (:abiertas) AND s.fecha < :limite %s
                    AND COALESCE((SELECT MAX(sp.hora) FROM salida_parada sp WHERE sp.salida_id = s.id), s.fecha + INTERVAL '1 day') < :limite
                  ORDER BY s.fecha",
                $empresa !== null ? "AND s.empresa_id = :empresa" : "",
            ),
            [
                "abiertas" => [EstadoSalida::PROGRAMADA->value, EstadoSalida::ABORDANDO->value, EstadoSalida::INICIADA->value],
                "limite" => $p->ahora->modify(sprintf("-%d hours", Tablero::HORAS_SIN_CERRAR))->format("Y-m-d H:i:s"),
            ] + ($empresa !== null ? ["empresa" => $empresa] : []),
            ["abiertas" => ArrayParameterType::STRING],
        );

        return array_map(static fn(array $f) => [
            "id" => (int) $f["id"],
            "fecha" => (string) $f["fecha"],
            "ruta" => (string) $f["ruta"],
            "estado" => (string) $f["estado"],
            "llegada" => (string) $f["llegada"],
        ], $filas);
    }

    /**
     * Agencias activas en la moneda principal con lo que vendieron los
     * últimos 7 días. Con empresa, las suyas y las que le vendieron en el
     * último mes.
     *
     * @return list<array{id: int, nombre: string, saldo: int, moneda: string, ventas7: int}>
     */
    private function agencias(Periodo $p, ?int $empresa): array
    {
        $filas = $this->db->fetchAllAssociative(
            sprintf(
                "SELECT a.id, a.nombre, a.saldo, a.moneda,
                        COALESCE((SELECT SUM(b.precio_monto)
                                    FROM boleto_venta v JOIN boleto_asiento b ON b.boleto_venta_id = v.id
                                   WHERE v.agencia_id = a.id AND v.estado = 'confirmada' AND v.created_at >= :semana
                                     AND b.estado NOT IN ('anulado', 'reasignado')), 0) AS ventas7
                   FROM agencia a
                  WHERE a.activo AND a.moneda = :moneda %s
                  ORDER BY a.nombre",
                $empresa !== null
                    ? "AND (EXISTS (SELECT 1 FROM agencia_empresa ae WHERE ae.agencia_id = a.id AND ae.empresa_id = :empresa) OR EXISTS (SELECT 1 FROM boleto_venta v JOIN boleto_asiento b ON b.boleto_venta_id = v.id JOIN salida s ON s.id = b.salida_id
                                                                 WHERE v.agencia_id = a.id AND s.empresa_id = :empresa AND v.created_at >= :mes))"
                    : "",
            ),
            [
                "semana" => $p->ahora->modify("-7 days")->format("Y-m-d H:i:s"),
                "moneda" => self::MONEDA,
            ] + ($empresa !== null ? ["empresa" => $empresa, "mes" => $p->ahora->modify("-30 days")->format("Y-m-d H:i:s")] : []),
        );

        return array_map(static fn(array $f) => [
            "id" => (int) $f["id"],
            "nombre" => (string) $f["nombre"],
            "saldo" => (int) $f["saldo"],
            "moneda" => (string) $f["moneda"],
            "ventas7" => (int) $f["ventas7"],
        ], $filas);
    }

    /** @return array{pendientes: int, conError: int, masAntigua: ?string} */
    private function facturacion(?int $empresa): array
    {
        $f = $this->db->fetchAssociative(
            sprintf(
                "SELECT COUNT(*) AS pendientes,
                        COUNT(*) FILTER (WHERE v.error_facturacion IS NOT NULL AND v.error_facturacion <> '') AS con_error,
                        MIN(v.created_at) AS mas_antigua
                   FROM boleto_venta v
                  WHERE v.estado = 'confirmada' AND v.estado_facturacion = 'pendiente' %s",
                $empresa !== null ? "AND EXISTS (SELECT 1 FROM boleto_asiento b JOIN salida s ON s.id = b.salida_id WHERE b.boleto_venta_id = v.id AND s.empresa_id = :empresa)" : "",
            ),
            $empresa !== null ? ["empresa" => $empresa] : [],
        ) ?: [];

        return [
            "pendientes" => (int) ($f["pendientes"] ?? 0),
            "conError" => (int) ($f["con_error"] ?? 0),
            "masAntigua" => isset($f["mas_antigua"]) ? (string) $f["mas_antigua"] : null,
        ];
    }

    /** @return array{pendientes: int, importe: int} */
    private function depositos(?int $empresa): array
    {
        $f = $this->db->fetchAssociative(
            sprintf(
                "SELECT COUNT(*) AS pendientes, COALESCE(SUM(d.importe), 0) AS importe
                   FROM agencia_deposito d JOIN agencia a ON a.id = d.agencia_id
                  WHERE d.estado = :registrado %s",
                $empresa !== null ? "AND EXISTS (SELECT 1 FROM agencia_empresa ae WHERE ae.agencia_id = a.id AND ae.empresa_id = :empresa)" : "",
            ),
            ["registrado" => EstadoDepositoAgencia::REGISTRADO->value] + ($empresa !== null ? ["empresa" => $empresa] : []),
        ) ?: [];

        return ["pendientes" => (int) ($f["pendientes"] ?? 0), "importe" => (int) ($f["importe"] ?? 0)];
    }

    /**
     * Buses en uso (con salidas del último mes o de la próxima semana) y los
     * de ellos con la tarjeta de operaciones vencida o por vencer en 30 días.
     * Las fechas anteriores al 2000 son relleno del legado: se ignoran.
     *
     * @return array{buses: int, vencidas: list<array{id: int, codigo: string, vence: string}>, porVencer: list<array{id: int, codigo: string, vence: string}>}
     */
    private function flota(Periodo $p, ?int $empresa): array
    {
        $filas = $this->db->fetchAllAssociative(
            sprintf(
                "SELECT b.id, COALESCE(b.codigo, b.matricula, b.id::text) AS codigo, b.fechavencimientotarjetaoperaciones AS vence
                   FROM bus b
                  WHERE EXISTS (SELECT 1 FROM salida s WHERE s.bus_id = b.id AND s.estado <> 'cancelada' AND s.fecha >= :desde AND s.fecha < :hasta %s)
                  ORDER BY b.fechavencimientotarjetaoperaciones NULLS LAST, 2",
                $empresa !== null ? "AND s.empresa_id = :empresa" : "",
            ),
            [
                "desde" => $p->hoy()->modify("-30 days")->format("Y-m-d H:i:s"),
                "hasta" => $p->hoy()->modify("+8 days")->format("Y-m-d H:i:s"),
            ] + ($empresa !== null ? ["empresa" => $empresa] : []),
        );

        $hoy = $p->hoy()->format("Y-m-d");
        $limite = $p->hoy()->modify("+30 days")->format("Y-m-d");
        $vencidas = [];
        $porVencer = [];
        foreach ($filas as $f) {
            $vence = $f["vence"] !== null ? substr((string) $f["vence"], 0, 10) : null;
            if ($vence === null || $vence < "2000-01-01") {
                continue;
            }
            $bus = ["id" => (int) $f["id"], "codigo" => (string) $f["codigo"], "vence" => $vence];
            if ($vence < $hoy) {
                $vencidas[] = $bus;
            } elseif ($vence <= $limite) {
                $porVencer[] = $bus;
            }
        }

        return ["buses" => count($filas), "vencidas" => $vencidas, "porVencer" => $porVencer];
    }

    /** Condición de las dos ventanas (actual y anterior) sobre una columna de fecha. */
    private static function ventanas(string $columna): string
    {
        return "(({$columna} >= :inicio AND {$columna} <= :ahora) OR ({$columna} >= :inicioAnterior AND {$columna} <= :finAnterior))";
    }

    private static function deEmpresa(?int $empresa): string
    {
        return $empresa !== null ? "AND s.empresa_id = :empresa" : "";
    }

    /** @return array<string, string|int> */
    private function parametros(Periodo $p, ?int $empresa): array
    {
        return [
            "inicio" => $p->inicio->format("Y-m-d H:i:s"),
            "ahora" => $p->ahora->format("Y-m-d H:i:s"),
            "inicioAnterior" => $p->inicioAnterior->format("Y-m-d H:i:s"),
            "finAnterior" => $p->finAnterior->format("Y-m-d H:i:s"),
            "moneda" => self::MONEDA,
        ] + ($empresa !== null ? ["empresa" => $empresa] : []);
    }
}
