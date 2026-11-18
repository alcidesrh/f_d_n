<?php

declare(strict_types=1);

namespace App\Reporte;

use App\Acceso\Alcance;
use App\Acceso\Ambito;
use App\Acceso\FiltroAlcance;
use App\Entity\BoletoAsiento;
use App\Entity\Empresa;
use App\Entity\Enum\CanalVenta;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoBoletoVenta;
use App\Entity\Estacion;
use App\Entity\Moneda;
use App\Entity\PagoWeb;
use App\Entity\Parada;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/** Lee de la base los boletos vendidos y arma los reportes de venta. Solo lectura. */
final class ConsultaReportes
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /** Con `$alcance`, solo lo vendido en sus empresas y estaciones (ADR-039). */
    public function cuadre(FiltroCuadre $f, ?Alcance $alcance = null): CuadreVentaBoletos
    {
        $inicio = $f->fecha;
        $fin = $inicio->modify("+1 day");

        $ventas = $this->base($f->empresaId, $alcance)
            ->andWhere("v.createdAt >= :inicio AND v.createdAt < :fin")
            ->andWhere("b.precio.moneda = :moneda")
            ->setParameter("inicio", $inicio)
            ->setParameter("fin", $fin)
            ->setParameter("moneda", $f->moneda);
        $this->deEstacion($ventas, $f->estacionId);

        $otras = [];
        if ($f->estacionId !== null) {
            $consulta = $this->base($f->empresaId, $alcance === null ? null : new Alcance(array_map(
                // Lo vendido en otras estaciones que sube en la suya: cuenta solo la empresa.
                static fn(Ambito $a) => new Ambito($a->empresas, null),
                $alcance->ambitos(),
            )))
                ->andWhere("s.fecha >= :inicio AND s.fecha < :fin")
                ->andWhere("v.createdAt < :inicio")
                ->andWhere("b.estado <> :anulado")
                ->andWhere("v.estacion IS NOT NULL AND v.estacion <> :estacion")
                ->andWhere("t.origen = :estacion")
                ->andWhere("b.precio.moneda = :moneda")
                ->setParameter("inicio", $inicio)
                ->setParameter("fin", $fin)
                ->setParameter("anulado", EstadoBoletoAsiento::ANULADO->value)
                ->setParameter("estacion", $f->estacionId)
                ->setParameter("moneda", $f->moneda);
            $otras = $this->lineas($consulta);
        }

        return new CuadreVentaBoletos(
            $inicio,
            $this->nombreEstacion($f->estacionId),
            $this->nombreEmpresa($f->empresaId),
            $f->moneda,
            $this->lineas($ventas),
            $otras,
        );
    }

    public function detalle(FiltroDetalle $f, ?Alcance $alcance = null): DetalleFacturaBoletos
    {
        $consulta = $this->base($f->empresaId, $alcance)
            ->andWhere("v.createdAt >= :inicio AND v.createdAt < :fin")
            ->andWhere("b.estado <> :anulado")
            ->setParameter("inicio", $f->desde)
            ->setParameter("fin", $f->hasta->modify("+1 day"))
            ->setParameter("anulado", EstadoBoletoAsiento::ANULADO->value);
        $this->deEstacion($consulta, $f->estacionId);

        if ($f->autorizacion !== null) {
            $consulta->andWhere("v.referenciaPago LIKE :autorizacion")
                ->setParameter("autorizacion", "%" . self::escapar($f->autorizacion) . "%");
        }
        if ($f->soloTarjetas) {
            $consulta->andWhere("v.referenciaPago IS NOT NULL");
        }
        if ($f->referencia !== null || $f->soloReferencias) {
            $consulta->andWhere(sprintf(
                "EXISTS (SELECT 1 FROM %s pr WHERE (pr.boletoVenta = v OR pr.boletoVentaRegreso = v) AND pr.referenciaPasarela IS NOT NULL%s)",
                PagoWeb::class,
                $f->referencia !== null ? " AND pr.referenciaPasarela LIKE :referencia" : "",
            ));
            if ($f->referencia !== null) {
                $consulta->setParameter("referencia", "%" . self::escapar($f->referencia) . "%");
            }
        }

        return new DetalleFacturaBoletos(
            $f->rotulo(),
            $this->nombreEstacion($f->estacionId),
            $this->nombreEmpresa($f->empresaId),
            $this->lineas($consulta),
        );
    }

    /** Boletos emitidos como voucher o cortesía (no se cobran) en el rango de fechas de venta. Los anulados no cuentan. */
    public function voucher(FiltroVoucher $f, ?Alcance $alcance = null): CuadreVoucher
    {
        $consulta = $this->base($f->empresaId, $alcance)
            ->andWhere("v.createdAt >= :inicio AND v.createdAt < :fin")
            ->andWhere("v.voucher = true OR v.cortesia = true")
            ->andWhere("b.estado <> :anulado")
            ->setParameter("inicio", $f->desde)
            ->setParameter("fin", $f->hasta->modify("+1 day"))
            ->setParameter("anulado", EstadoBoletoAsiento::ANULADO->value);
        $this->deEstacion($consulta, $f->estacionId);

        return new CuadreVoucher(
            $f->rotulo(),
            $this->nombreEstacion($f->estacionId),
            $this->nombreEmpresa($f->empresaId),
            $this->lineas($consulta),
        );
    }

    /**
     * @param list<int>|null $ids solo esas (el alcance); null = las activas
     *
     * @return list<array{id: int, nombre: string, departamento: ?string}>
     */
    public function estaciones(?array $ids = null): array
    {
        $qb = $this->em->createQueryBuilder()->select("e.id", "e.nombre", "e.departamento")->from(Estacion::class, "e")->orderBy("e.nombre");
        if ($ids !== null) {
            $qb->andWhere("e.id IN (:ids)")->setParameter("ids", $ids === [] ? [0] : $ids);
        } else {
            $qb->andWhere("e.activo = true");
        }

        return array_map(static fn(array $r) => ["id" => (int) $r["id"], "nombre" => (string) $r["nombre"], "departamento" => $r["departamento"]], $qb->getQuery()->getArrayResult());
    }

    /**
     * Las activas, para los filtros.
     *
     * @param list<int>|null $ids solo esas (el alcance); null = todas
     *
     * @return list<array{id: int, nombre: string}>
     */
    public function empresas(?array $ids = null): array
    {
        $empresas = $this->em->getRepository(Empresa::class)->findBy(["activa" => true] + ($ids === null ? [] : ["id" => $ids]), ["nombre" => "ASC"]);

        return array_values(array_map(static fn(Empresa $e) => ["id" => (int) $e->getId(), "nombre" => $e->getNombreCorto()], $empresas));
    }

    /** @return list<array{id: int, sigla: string, nombre: string}> */
    public function monedas(): array
    {
        return array_map(
            static fn(Moneda $m) => ["id" => (int) $m->getId(), "sigla" => (string) $m->getSigla(), "nombre" => (string) $m->getNombre()],
            $this->em->getRepository(Moneda::class)->findBy(["activo" => true], ["sigla" => "ASC"]),
        );
    }

    /**
     * Boletos de ventas confirmadas, sin reasignados: solo las columnas que
     * lleva cada `LineaBoleto`. Sin hidratar entidades: un mes son decenas de
     * miles de boletos con sus 13 relaciones (y el XML de cada factura).
     */
    private function base(?int $empresaId, ?Alcance $alcance = null): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder()
            ->select(
                "b.id AS boletoId",
                "b.estado AS estado",
                "b.precio.monto AS centavos",
                "b.precio.moneda AS moneda",
                "a.numero AS asiento",
                "v.id AS ventaId",
                "v.createdAt AS vendida",
                "v.voucher AS voucher",
                "v.cortesia AS cortesia",
                "v.canal AS canal",
                "v.estadoFacturacion AS estadoFacturacion",
                "v.referenciaPago AS autorizacion",
                "u.username AS usuario",
                "u.nombre AS usuarioNombre",
                "u.apellido AS usuarioApellido",
                "f.id AS facturaId",
                "f.serie AS serie",
                "f.dte AS dte",
                "tp.nombre AS tipoPago",
                "est.nombre AS estacionVenta",
                "s.id AS salidaId",
                "s.fecha AS salida",
                "IDENTITY(s.ruta) AS rutaId",
                "bus.codigo AS busCodigo",
                "pil.codigo AS pilotoCodigo",
                "o.nombre AS origen",
                "d.nombre AS destino",
            )
            ->from(BoletoAsiento::class, "b")
            ->join("b.asiento", "a")
            ->join("b.boletoVenta", "v")
            ->join("b.salida", "s")
            ->join("b.trayecto", "t")
            ->join("t.origen", "o")
            ->join("t.destino", "d")
            ->join("s.ruta", "sr")
            ->leftJoin("s.bus", "bus")
            ->leftJoin("bus.piloto", "pil")
            ->leftJoin("v.usuario", "u")
            ->leftJoin("v.factura", "f")
            ->leftJoin("v.tipoPago", "tp")
            ->leftJoin("v.estacion", "est")
            ->where("v.estado = :confirmada")
            ->andWhere("b.estado <> :reasignado")
            ->setParameter("confirmada", EstadoBoletoVenta::CONFIRMADA->value)
            ->setParameter("reasignado", EstadoBoletoAsiento::REASIGNADO->value)
            ->orderBy("v.createdAt")
            ->addOrderBy("b.id");
        if ($empresaId !== null) {
            $qb->andWhere("s.empresa = :empresa")->setParameter("empresa", $empresaId);
        }
        if ($alcance !== null) {
            FiltroAlcance::aplicar($qb, $alcance, "s.empresa", "v.estacion");
        }

        return $qb;
    }

    private function deEstacion(QueryBuilder $qb, ?int $estacionId): void
    {
        if ($estacionId !== null) {
            $qb->andWhere("v.estacion = :estacionVenta")->setParameter("estacionVenta", $estacionId);
        }
    }

    /** @return list<LineaBoleto> */
    private function lineas(QueryBuilder $qb): array
    {
        /** @var list<array<string, mixed>> $filas */
        $filas = $qb->getQuery()->getArrayResult();
        $ventaIds = array_values(array_unique(array_map(static fn(array $f) => (int) $f["ventaId"], $filas)));
        $referencias = $this->referencias($ventaIds);
        $puntas = $this->puntas(array_values(array_unique(array_map(static fn(array $f) => (int) $f["rutaId"], $filas))));

        return array_map(static function (array $f) use ($referencias, $puntas): LineaBoleto {
            $estado = self::valor($f["estado"]);
            $autorizacion = $f["autorizacion"];

            return new LineaBoleto(
                (int) $f["boletoId"],
                (int) $f["ventaId"],
                \DateTimeImmutable::createFromInterface($f["vendida"] ?? new \DateTime()),
                $f["usuario"] ?? "—",
                $f["usuario"] === null ? "" : trim($f["usuarioNombre"] . " " . $f["usuarioApellido"]),
                $estado === EstadoBoletoAsiento::ANULADO->value,
                (int) $f["centavos"],
                (string) $f["moneda"],
                $f["voucher"] || $f["cortesia"],
                (int) $f["salidaId"],
                \DateTimeImmutable::createFromInterface($f["salida"]),
                $f["busCodigo"],
                $f["pilotoCodigo"],
                $puntas[(int) $f["rutaId"]] ?? "? - ?",
                (string) $f["origen"],
                (string) $f["destino"],
                (int) $f["asiento"],
                $f["facturaId"] === null ? null : (int) $f["facturaId"],
                $f["serie"],
                $f["dte"] === null ? null : (int) $f["dte"],
                self::valor($f["estadoFacturacion"]),
                self::valor($f["canal"]) === CanalVenta::WEB->value || stripos((string) $f["tipoPago"], "tarjeta") !== false,
                $autorizacion !== null && $autorizacion !== "" ? $autorizacion : null,
                $referencias[(int) $f["ventaId"]] ?? null,
                $estado,
                (string) $f["estacionVenta"],
            );
        }, $filas);
    }

    /**
     * "Origen - Destino" de cada ruta (su primera y su última parada), en una consulta.
     *
     * @param list<int> $rutaIds
     * @return array<int, string>
     */
    private function puntas(array $rutaIds): array
    {
        if ($rutaIds === []) {
            return [];
        }
        $filas = $this->em->createQueryBuilder()
            ->select("IDENTITY(p.ruta) AS ruta", "e.nombre AS nombre")
            ->from(Parada::class, "p")
            ->join("p.enclave", "e")
            ->where("p.ruta IN (:rutas)")
            ->setParameter("rutas", $rutaIds, ArrayParameterType::INTEGER)
            ->orderBy("p.orden")
            ->getQuery()
            ->getArrayResult();
        $extremos = [];
        foreach ($filas as $fila) {
            $ruta = (int) $fila["ruta"];
            $extremos[$ruta] ??= [$fila["nombre"], null];
            $extremos[$ruta][1] = $fila["nombre"];
        }

        return array_map(static fn(array $e) => sprintf("%s - %s", $e[0] ?? "?", $e[1] ?? "?"), $extremos);
    }

    /** Un enum de Doctrine llega como enum o como su valor, según la versión del hidratador. */
    private static function valor(mixed $valor): string
    {
        return $valor instanceof \BackedEnum ? (string) $valor->value : (string) $valor;
    }

    /**
     * Referencia de la pasarela por id de venta (ida y regreso del pago web).
     *
     * @param list<int> $ids
     * @return array<int, string>
     */
    private function referencias(array $ids): array
    {
        $mapa = [];
        foreach (array_chunk($ids, 1000) as $lote) {
            $filas = $this->em->createQueryBuilder()
                ->select("IDENTITY(p.boletoVenta) AS ida", "IDENTITY(p.boletoVentaRegreso) AS regreso", "p.referenciaPasarela AS referencia")
                ->from(PagoWeb::class, "p")
                ->where("p.referenciaPasarela IS NOT NULL")
                ->andWhere("p.boletoVenta IN (:ids) OR p.boletoVentaRegreso IN (:ids)")
                ->setParameter("ids", $lote, ArrayParameterType::INTEGER)
                ->getQuery()
                ->getArrayResult();
            foreach ($filas as $fila) {
                foreach (["ida", "regreso"] as $campo) {
                    if ($fila[$campo] !== null) {
                        $mapa[(int) $fila[$campo]] = (string) $fila["referencia"];
                    }
                }
            }
        }

        return $mapa;
    }

    private function nombreEstacion(?int $id): ?string
    {
        return $id === null ? null : $this->em->find(Estacion::class, $id)?->getNombre();
    }

    private function nombreEmpresa(?int $id): ?string
    {
        return $id === null ? null : $this->em->find(Empresa::class, $id)?->getNombreCorto();
    }

    private static function escapar(string $texto): string
    {
        return addcslashes($texto, "%_\\");
    }
}
