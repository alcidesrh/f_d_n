<?php

declare(strict_types=1);

namespace App\Venta\EnLinea;

use App\Entity\BoletoAsiento;
use App\Entity\Enum\TransicionBoleto;
use App\Venta\Boleto\DatosBoleto;
use App\Venta\Boleto\Flujo\FlujoBoleto;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Money\Currency;
use Money\Money;

/**
 * Compras de la página web para el dashboard (ADR-023): cada intento de pago
 * (`pago_web`) con sus ventas (ida y regreso), y la "calculadora" con los
 * totales de todo lo filtrado (no solo de la página visible). Solo lectura.
 * Cada boleto trae lo que admite de las acciones de la compra (anular,
 * reasignar, posponer): las transiciones del workflow del boleto (ADR-037).
 */
final class ConsultaComprasWeb
{
    private const COLUMNAS = [
        "p.id", "p.token", "p.estado", "p.creado", "p.actualizado", "p.monto_monto", "p.monto_moneda",
        "p.marca", "p.ultimos4", "p.autorizacion", "p.referencia_pasarela", "p.mensaje", "p.comprador",
        "p.recargo_porciento", "p.viajes", "p.boleto_venta_id", "p.boleto_venta_regreso_id",
        "e.id AS empresa_id", "COALESCE(e.alias, e.nombre) AS empresa_nombre",
    ];

    /** Las acciones de la compra que son transiciones de sus boletos. */
    private const TRANSICIONES = [TransicionBoleto::ANULAR, TransicionBoleto::REASIGNAR, TransicionBoleto::POSPONER];

    public function __construct(
        private readonly Connection $db,
        private readonly EntityManagerInterface $em,
        private readonly FlujoBoleto $flujo,
    ) {}

    /** Una compra, como en el listado (para refrescar la fila tras una acción). */
    public function una(int $id): ?array
    {
        $fila = $this->db->createQueryBuilder()
            ->select(...self::COLUMNAS)
            ->from("pago_web", "p")
            ->leftJoin("p", "empresa", "e", "e.id = p.empresa_id")
            ->where("p.id = :id")
            ->setParameter("id", $id)
            ->executeQuery()
            ->fetchAssociative();

        return $fila === false ? null : $this->items([$fila])[0];
    }

    /**
     * @param list<array<string, mixed>> $filas
     *
     * @return list<array<string, mixed>>
     */
    private function items(array $filas): array
    {
        $ventas = $this->ventas(array_values(array_filter(array_merge(
            array_column($filas, "boleto_venta_id"),
            array_column($filas, "boleto_venta_regreso_id"),
        ))));

        return array_map(fn(array $p) => $this->item($p, $ventas), $filas);
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, pagina: int, porPagina: int, resumen: array<string, mixed>}
     */
    public function buscar(FiltroComprasWeb $f): array
    {
        $total = (int) $this->filtrado($f)->select("COUNT(*)")->executeQuery()->fetchOne();

        $orden = match ($f->orden) {
            "monto" => "p.monto_monto",
            "salida" => "(SELECT MIN(s.fecha) FROM boleto_asiento b JOIN salida s ON s.id = b.salida_id WHERE b.boleto_venta_id = p.boleto_venta_id)",
            default => "p.creado",
        };
        $filas = $this->filtrado($f)
            ->select(...self::COLUMNAS)
            ->orderBy($orden, $f->descendente ? "DESC" : "ASC")
            ->addOrderBy("p.id", $f->descendente ? "DESC" : "ASC")
            ->setFirstResult(($f->pagina - 1) * $f->porPagina)
            ->setMaxResults($f->porPagina)
            ->executeQuery()
            ->fetchAllAssociative();

        return [
            "items" => $this->items($filas),
            "total" => $total,
            "pagina" => $f->pagina,
            "porPagina" => $f->porPagina,
            "resumen" => $this->resumen($f),
        ];
    }

    /**
     * Totales de todo lo filtrado: la calculadora del dashboard. Lo cobrado,
     * el recargo y los promedios cuentan solo las compras completadas.
     */
    private function resumen(FiltroComprasWeb $f): array
    {
        $g = $this->filtrado($f)
            ->select(
                "COUNT(*) AS compras",
                "COUNT(*) FILTER (WHERE p.estado = 'completado') AS completadas",
                "COALESCE(SUM(p.monto_monto), 0) AS monto",
                "COALESCE(SUM(p.monto_monto) FILTER (WHERE p.estado = 'completado'), 0) AS cobrado",
                "COALESCE(SUM(ROUND(p.monto_monto - p.monto_monto / (1 + p.recargo_porciento / 100))) FILTER (WHERE p.estado = 'completado'), 0) AS recargo",
                "COUNT(*) FILTER (WHERE p.viajes = 2 AND p.estado = 'completado') AS ida_vuelta",
                "MIN(p.monto_moneda) AS moneda",
            )
            ->executeQuery()
            ->fetchAssociative();

        $asientos = (int) $this->db->createQueryBuilder()
            ->select("COUNT(b.id)")
            ->from("boleto_asiento", "b")
            ->where("b.boleto_venta_id IN (" . $this->idsVentas($f) . ")")
            ->andWhere("b.estado <> 'reasignado'")
            ->setParameters($this->parametros($f), $this->tipos($f))
            ->executeQuery()
            ->fetchOne();

        $porEstado = $this->filtrado($f)
            ->select("p.estado", "COUNT(*) AS compras", "COALESCE(SUM(p.monto_monto), 0) AS monto")
            ->groupBy("p.estado")
            ->orderBy("compras", "DESC")
            ->executeQuery()
            ->fetchAllAssociative();

        $porEmpresa = $this->filtrado($f)
            ->select("COALESCE(e.alias, e.nombre, '—') AS empresa", "COUNT(*) AS compras", "COALESCE(SUM(p.monto_monto), 0) AS monto")
            ->andWhere("p.estado = 'completado'")
            ->groupBy("e.alias", "e.nombre")
            ->orderBy("monto", "DESC")
            ->executeQuery()
            ->fetchAllAssociative();

        $moneda = new Currency($g["moneda"] ?: "GTQ");
        $importe = static fn(int|string $c) => DatosBoleto::importe(new Money((int) $c, $moneda));
        $completadas = (int) $g["completadas"];
        $cobrado = (int) $g["cobrado"];

        return [
            "compras" => (int) $g["compras"],
            "completadas" => $completadas,
            "idaVuelta" => (int) $g["ida_vuelta"],
            // Los asientos son de las ventas registradas: solo las compras completadas tienen.
            "asientos" => $asientos,
            "monto" => $importe((int) $g["monto"]),
            "cobrado" => $importe($cobrado),
            "recargo" => $importe((int) $g["recargo"]),
            "promedioCompra" => $importe($completadas > 0 ? intdiv($cobrado, $completadas) : 0),
            "promedioAsiento" => $importe($asientos > 0 ? intdiv($cobrado, $asientos) : 0),
            "porEstado" => array_map(static fn(array $r) => ["estado" => $r["estado"], "compras" => (int) $r["compras"], "monto" => $importe($r["monto"])], $porEstado),
            "porEmpresa" => array_map(static fn(array $r) => ["empresa" => $r["empresa"], "compras" => (int) $r["compras"], "monto" => $importe($r["monto"])], $porEmpresa),
        ];
    }

    /** `pago_web p` con el filtro aplicado (sin SELECT). */
    private function filtrado(FiltroComprasWeb $f): QueryBuilder
    {
        $qb = $this->db->createQueryBuilder()
            ->from("pago_web", "p")
            ->leftJoin("p", "empresa", "e", "e.id = p.empresa_id")
            ->where("1 = 1");

        if ($f->estados !== []) {
            $qb->andWhere("p.estado IN (:estados)");
        }
        if ($f->creadoDesde !== null) {
            $qb->andWhere("p.creado >= :creadoDesde");
        }
        if ($f->creadoHasta !== null) {
            $qb->andWhere("p.creado < CAST(:creadoHasta AS date) + 1");
        }
        if ($f->idaVuelta !== null) {
            $qb->andWhere($f->idaVuelta ? "p.viajes = 2" : "p.viajes = 1");
        }
        if ($f->marca !== null) {
            $qb->andWhere("p.marca = :marca");
        }
        if ($f->montoMinimo !== null) {
            $qb->andWhere("p.monto_monto >= :montoMinimo");
        }
        if ($f->montoMaximo !== null) {
            $qb->andWhere("p.monto_monto <= :montoMaximo");
        }
        if ($f->texto !== null) {
            $qb->andWhere(implode(" OR ", [
                "CAST(p.comprador AS text) ILIKE :texto",
                "p.ultimos4 = :textoExacto",
                "p.autorizacion ILIKE :texto",
                "p.referencia_pasarela ILIKE :texto",
                "CAST(p.token AS text) ILIKE :texto",
                "CAST(p.boleto_venta_id AS text) = :textoNumero",
                "CAST(p.boleto_venta_regreso_id AS text) = :textoNumero",
            ]));
        }
        if ($f->facturacion !== null) {
            $qb->andWhere("EXISTS (SELECT 1 FROM boleto_venta vf WHERE vf.id IN (p.boleto_venta_id, p.boleto_venta_regreso_id) AND vf.estado_facturacion = :facturacion)");
        }

        $viaje = [];
        if ($f->salidaDesde !== null) {
            $viaje[] = "s.fecha >= :salidaDesde";
        }
        if ($f->salidaHasta !== null) {
            $viaje[] = "s.fecha < CAST(:salidaHasta AS date) + 1";
        }
        if ($f->empresa !== null) {
            $viaje[] = "s.empresa_id = :empresa";
        }
        if ($f->origen !== null) {
            $viaje[] = "t.origen_id = :origen";
        }
        if ($f->destino !== null) {
            $viaje[] = "t.destino_id = :destino";
        }
        if ($viaje !== []) {
            // Algún viaje de la compra (ida o regreso) cumple todas las condiciones.
            $qb->andWhere(sprintf(
                "EXISTS (SELECT 1 FROM boleto_asiento b JOIN salida s ON s.id = b.salida_id JOIN trayecto t ON t.id = b.trayecto_id WHERE b.boleto_venta_id IN (p.boleto_venta_id, p.boleto_venta_regreso_id) AND %s)",
                implode(" AND ", $viaje),
            ));
        }

        return $qb->setParameters($this->parametros($f), $this->tipos($f));
    }

    /** SQL con los ids de venta de las compras filtradas (ida y regreso). */
    private function idsVentas(FiltroComprasWeb $f): string
    {
        $sql = $this->filtrado($f)->select("p.boleto_venta_id")->getSQL();
        $regreso = $this->filtrado($f)->select("p.boleto_venta_regreso_id")->getSQL();

        return "{$sql} UNION ALL {$regreso}";
    }

    /** @return array<string, mixed> */
    private function parametros(FiltroComprasWeb $f): array
    {
        return array_filter([
            "estados" => $f->estados !== [] ? $f->estados : null,
            "creadoDesde" => $f->creadoDesde,
            "creadoHasta" => $f->creadoHasta,
            "salidaDesde" => $f->salidaDesde,
            "salidaHasta" => $f->salidaHasta,
            "empresa" => $f->empresa,
            "origen" => $f->origen,
            "destino" => $f->destino,
            "facturacion" => $f->facturacion,
            "marca" => $f->marca,
            "montoMinimo" => $f->montoMinimo,
            "montoMaximo" => $f->montoMaximo,
            "texto" => $f->texto !== null ? "%" . addcslashes($f->texto, "%_\\") . "%" : null,
            "textoExacto" => $f->texto,
            "textoNumero" => $f->texto !== null ? ltrim($f->texto, "0") : null,
        ], static fn($v) => $v !== null);
    }

    /** @return array<string, ArrayParameterType> */
    private function tipos(FiltroComprasWeb $f): array
    {
        return $f->estados !== [] ? ["estados" => ArrayParameterType::STRING] : [];
    }

    /**
     * Resumen de cada venta: viaje, asientos y factura. Sin los boletos
     * reasignados: su reemplazo, en la misma venta, es el viaje vigente.
     *
     * @param list<int|string> $ids
     *
     * @return array<int, array<string, mixed>>
     */
    private function ventas(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $filas = $this->db->executeQuery(
            "SELECT v.id, v.estado_facturacion, v.total_monto, v.total_moneda, v.error_facturacion, v.correo_enviado_en,
                    f.serie, f.dte, f.uuid, f.url_pdf, f.anulada_en,
                    JSON_AGG(JSON_BUILD_OBJECT('id', b.id, 'estado', b.estado, 'asiento', a.numero, 'salidaId', s.id, 'salida', s.fecha) ORDER BY b.id) AS boletos,
                    MIN(s.fecha) AS salida, MIN(s.id) AS salida_id,
                    MIN(o.nombre) AS origen, MIN(d.nombre) AS destino,
                    MIN(COALESCE(em.alias, em.nombre)) AS empresa,
                    STRING_AGG(CAST(a.numero AS text), ', ' ORDER BY a.numero) AS asientos,
                    COUNT(b.id) AS cantidad
             FROM boleto_venta v
             LEFT JOIN factura f ON f.id = v.factura_id
             JOIN boleto_asiento b ON b.boleto_venta_id = v.id
             JOIN asiento a ON a.id = b.asiento_id
             JOIN salida s ON s.id = b.salida_id
             JOIN trayecto t ON t.id = b.trayecto_id
             JOIN enclave o ON o.id = t.origen_id
             JOIN enclave d ON d.id = t.destino_id
             LEFT JOIN empresa em ON em.id = s.empresa_id
             WHERE v.id IN (:ids) AND b.estado <> 'reasignado'
             GROUP BY v.id, f.id",
            ["ids" => array_map("intval", $ids)],
            ["ids" => ArrayParameterType::INTEGER],
        )->fetchAllAssociative();

        $boletos = array_map(static fn(array $v) => json_decode((string) $v["boletos"], true) ?: [], array_column($filas, null, "id"));
        $acciones = $this->acciones(array_merge(...array_map(static fn(array $bs) => array_map("intval", array_column($bs, "id")), array_values($boletos))));

        $porId = [];
        foreach ($filas as $v) {
            $porId[(int) $v["id"]] = [
                "id" => (int) $v["id"],
                "codigo" => sprintf("%08d", $v["id"]),
                "salidaId" => (int) $v["salida_id"],
                "salida" => (new \DateTimeImmutable($v["salida"]))->format(DATE_ATOM),
                "origen" => $v["origen"],
                "destino" => $v["destino"],
                "empresa" => $v["empresa"],
                "asientos" => $v["asientos"],
                "cantidad" => (int) $v["cantidad"],
                "total" => DatosBoleto::importe(new Money((int) $v["total_monto"], new Currency($v["total_moneda"]))),
                "facturacion" => $v["estado_facturacion"],
                "errorFacturacion" => $v["error_facturacion"],
                "factura" => $v["uuid"] ? ["serie" => $v["serie"], "numero" => $v["dte"], "uuid" => $v["uuid"], "urlPdf" => $v["url_pdf"], "anulada" => $v["anulada_en"] !== null] : null,
                "correoEnviado" => $v["correo_enviado_en"] !== null ? (new \DateTimeImmutable($v["correo_enviado_en"]))->format(DATE_ATOM) : null,
                "boletos" => array_map(static fn(array $b) => [
                    "id" => (int) $b["id"],
                    "estado" => $b["estado"],
                    "asiento" => (int) $b["asiento"],
                    "salidaId" => (int) $b["salidaId"],
                    "salida" => (new \DateTimeImmutable($b["salida"]))->format(DATE_ATOM),
                    "acciones" => $acciones[(int) $b["id"]],
                ], $boletos[$v["id"]]),
            ];
        }

        return $porId;
    }

    /**
     * Lo que admite cada boleto (null o el motivo, por transición), con las
     * entidades cargadas de una vez. El que no se carga es de una salida de
     * otra empresa (el filtro de empresa del usuario, ADR-015): no se opera.
     *
     * @param list<int> $ids
     *
     * @return array<int, array<string, ?string>>
     */
    private function acciones(array $ids): array
    {
        $otra = array_fill_keys(array_map(static fn(TransicionBoleto $t) => $t->value, self::TRANSICIONES), "El boleto es de una salida de otra empresa.");
        $acciones = array_fill_keys($ids, $otra);
        if ($ids === []) {
            return $acciones;
        }
        /** @var list<BoletoAsiento> $boletos */
        $boletos = $this->em->createQueryBuilder()
            ->select("b", "s", "v", "a")
            ->from(BoletoAsiento::class, "b")
            ->join("b.salida", "s")
            ->join("b.boletoVenta", "v")
            ->leftJoin("b.asiento", "a")
            ->where("b.id IN (:ids)")
            ->setParameter("ids", $ids)
            ->getQuery()
            ->getResult();
        foreach ($boletos as $b) {
            $acciones[(int) $b->getId()] = $this->flujo->motivos($b, ...self::TRANSICIONES);
        }

        return $acciones;
    }

    /**
     * @param array<string, mixed> $p
     * @param array<int, array<string, mixed>> $ventas
     *
     * @return array<string, mixed>
     */
    private function item(array $p, array $ventas): array
    {
        $comprador = json_decode((string) $p["comprador"], true) ?: [];

        return [
            "id" => (int) $p["id"],
            "token" => $p["token"],
            "estado" => $p["estado"],
            "creado" => (new \DateTimeImmutable($p["creado"]))->format(DATE_ATOM),
            "actualizado" => (new \DateTimeImmutable($p["actualizado"]))->format(DATE_ATOM),
            "monto" => DatosBoleto::importe(new Money((int) $p["monto_monto"], new Currency($p["monto_moneda"]))),
            "recargoPorciento" => $p["recargo_porciento"],
            "viajes" => (int) $p["viajes"],
            "tarjeta" => ["marca" => $p["marca"], "ultimos4" => $p["ultimos4"]],
            "autorizacion" => $p["autorizacion"],
            "referencia" => $p["referencia_pasarela"],
            "mensaje" => $p["mensaje"],
            "empresa" => $p["empresa_id"] !== null ? ["id" => (int) $p["empresa_id"], "nombre" => $p["empresa_nombre"]] : null,
            "comprador" => [
                "nombre" => trim(($comprador["nombre"] ?? "") . " " . ($comprador["apellido"] ?? "")),
                "nombres" => $comprador["nombre"] ?? "",
                "apellido" => $comprador["apellido"] ?? null,
                "email" => $comprador["email"] ?? null,
                "telefono" => $comprador["telefono"] ?? null,
                "nit" => $comprador["nit"] ?? "CF",
                "nombreFactura" => $comprador["nombreFactura"] ?? null,
            ],
            "ventas" => array_values(array_filter([
                $ventas[(int) $p["boleto_venta_id"]] ?? null,
                $ventas[(int) $p["boleto_venta_regreso_id"]] ?? null,
            ])),
        ];
    }
}
