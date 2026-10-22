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
     * @param list<int>|null $ids solo esas (el alcance); null = todas
     *
     * @return list<array{id: int, nombre: string}>
     */
    public function empresas(?array $ids = null): array
    {
        $empresas = $this->em->getRepository(Empresa::class)->findBy($ids === null ? [] : ["id" => $ids], ["nombre" => "ASC"]);

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

    /** Boletos de ventas confirmadas, sin reasignados, con todo lo que el reporte necesita ya cargado. */
    private function base(?int $empresaId, ?Alcance $alcance = null): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder()
            ->select("b", "a", "v", "u", "f", "tp", "s", "bus", "pil", "t", "o", "d", "sr")
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
        /** @var list<BoletoAsiento> $boletos */
        $boletos = $qb->getQuery()->getResult();
        $referencias = $this->referencias($boletos);

        return array_map(function (BoletoAsiento $b) use ($referencias): LineaBoleto {
            $venta = $b->getBoletoVenta();
            $salida = $b->getSalida();
            $ruta = $salida->getRuta();
            $factura = $venta->getFactura();
            $usuario = $venta->getUsuario();
            $precio = $b->getPrecio();
            $autorizacion = $venta->getReferenciaPago();

            return new LineaBoleto(
                (int) $b->getId(),
                (int) $venta->getId(),
                \DateTimeImmutable::createFromInterface($venta->getCreada() ?? new \DateTime()),
                $usuario?->getUsername() ?? "—",
                $usuario === null ? "" : trim((string) $usuario->getFullName()),
                $b->getEstado() === EstadoBoletoAsiento::ANULADO,
                $precio === null ? 0 : (int) $precio->getAmount(),
                $precio?->getCurrency()->getCode() ?? "GTQ",
                $venta->isVoucher() || $venta->isCortesia(),
                (int) $salida->getId(),
                \DateTimeImmutable::createFromInterface($salida->getFecha()),
                $salida->getBus()?->getCodigo(),
                $salida->getBus()?->getPiloto()?->getCodigo(),
                sprintf("%s - %s", $ruta->getOrigen()?->getNombre() ?? "?", $ruta->getDestino()?->getNombre() ?? "?"),
                (string) $b->getTrayecto()->getOrigen()?->getNombre(),
                (string) $b->getTrayecto()->getDestino()?->getNombre(),
                (int) $b->getAsiento()->getNumero(),
                $factura?->getId(),
                $factura?->getSerie(),
                $factura?->getDte(),
                $venta->getEstadoFacturacion()->value,
                $venta->getCanal() === CanalVenta::WEB || stripos((string) $venta->getTipoPago()?->getNombre(), "tarjeta") !== false,
                $autorizacion !== null && $autorizacion !== "" ? $autorizacion : null,
                $referencias[(int) $venta->getId()] ?? null,
                $b->getEstado()->value,
                (string) $venta->getEstacion()?->getNombre(),
            );
        }, $boletos);
    }

    /**
     * Referencia de la pasarela por id de venta (ida y regreso del pago web).
     *
     * @param list<BoletoAsiento> $boletos
     * @return array<int, string>
     */
    private function referencias(array $boletos): array
    {
        $ids = array_values(array_unique(array_map(static fn(BoletoAsiento $b) => (int) $b->getBoletoVenta()->getId(), $boletos)));
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
