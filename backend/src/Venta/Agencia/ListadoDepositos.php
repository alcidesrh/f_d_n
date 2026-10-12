<?php

declare(strict_types=1);

namespace App\Venta\Agencia;

use App\Entity\Agencia;
use App\Entity\AgenciaDeposito;
use App\Entity\Enum\EstadoDepositoAgencia;
use App\Venta\Boleto\DatosBoleto;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Money\Currency;
use Money\Money;

/**
 * Lecturas de la gestión de agencias (ADR-034): la página de depósitos y
 * los saldos. `$propia` (la agencia del usuario) limita todo a esa agencia.
 */
final class ListadoDepositos
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /** @return array{items: list<array<string, mixed>>, total: int, desde: string, hasta: string} */
    public function buscar(FiltroDepositos $f, ?int $propia): array
    {
        $qb = $this->em->createQueryBuilder()->from(AgenciaDeposito::class, "d");
        if ($f->id !== null) {
            $qb->andWhere("d.id = :id")->setParameter("id", $f->id);
        } else {
            $qb->andWhere("d.fecha BETWEEN :desde AND :hasta")
                ->setParameter("desde", $f->desde)
                ->setParameter("hasta", $f->hasta);
        }
        if ($f->agencia !== null) {
            $qb->andWhere("IDENTITY(d.agencia) = :agencia")->setParameter("agencia", $f->agencia);
        }
        if ($f->boleta !== null) {
            $qb->andWhere("LOWER(d.numeroBoleta) LIKE :boleta")->setParameter("boleta", "%" . mb_strtolower(addcslashes($f->boleta, "%_")) . "%");
        }
        if ($f->estados !== []) {
            $qb->andWhere("d.estado IN (:estados)")->setParameter("estados", array_map(static fn($e) => $e->value, $f->estados));
        }
        self::limitar($qb, $propia);

        $total = (int) (clone $qb)->select("COUNT(d.id)")->getQuery()->getSingleScalarResult();
        /** @var list<AgenciaDeposito> $depositos */
        $depositos = $qb->select("d", "a", "c", "r")
            ->join("d.agencia", "a")
            ->leftJoin("d.creadoPor", "c")
            ->leftJoin("d.resueltoPor", "r")
            ->orderBy("d.fecha", "DESC")
            ->addOrderBy("d.id", "DESC")
            ->setFirstResult(($f->pagina - 1) * $f->porPagina)
            ->setMaxResults($f->porPagina)
            ->getQuery()
            ->getResult();

        return [
            "items" => array_map(self::fila(...), $depositos),
            "total" => $total,
            "desde" => $f->desde->format("Y-m-d"),
            "hasta" => $f->hasta->format("Y-m-d"),
        ];
    }

    /** @return array<string, mixed> */
    public static function fila(AgenciaDeposito $d): array
    {
        $q = static fn(int $c) => DatosBoleto::importe(new Money($c, new Currency($d->getMoneda())));

        return [
            "id" => $d->getId(),
            "fecha" => $d->getFecha()->format("Y-m-d"),
            "agencia" => ["id" => $d->getAgencia()->getId(), "nombre" => $d->getAgencia()->getNombre()],
            "estado" => $d->getEstado()->value,
            "importe" => $q($d->getImporte()),
            "aplicaBono" => $d->isAplicaBono(),
            // Registrado: lo que se acreditaría hoy; acreditado: lo que se acreditó.
            "bono" => $q(DepositosAgencia::bonoPrevisto($d)),
            "numeroBoleta" => $d->getNumeroBoleta(),
            "observacion" => $d->getObservacion(),
            "motivoRechazo" => $d->getMotivoRechazo(),
            "creadoEn" => $d->getCreadoEn()->format(DATE_ATOM),
            "creadoPor" => $d->getCreadoPor()?->getUsername(),
            "resueltoEn" => $d->getResueltoEn()?->format(DATE_ATOM),
            "resueltoPor" => $d->getResueltoPor()?->getUsername(),
        ];
    }

    /**
     * Saldo de cada agencia con lo acreditado (depósitos y bonos) y lo que
     * espera acreditación. Como en el legado, "total depositado" suma los
     * depósitos acreditados (también los migrados).
     *
     * @return list<array<string, mixed>>
     */
    public function saldos(?int $propia): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select("a")
            ->from(Agencia::class, "a")
            ->orderBy("a.activo", "DESC")
            ->addOrderBy("a.nombre", "ASC");
        if ($propia !== null) {
            $qb->andWhere("a.id = :propia")->setParameter("propia", $propia);
        }
        /** @var list<Agencia> $agencias */
        $agencias = $qb->getQuery()->getResult();

        $sumas = [];
        $filas = $this->em->createQueryBuilder()
            ->select("IDENTITY(d.agencia) AS agencia", "d.estado AS estado", "SUM(d.importe) AS importe", "SUM(d.bono) AS bono", "COUNT(d.id) AS cantidad")
            ->from(AgenciaDeposito::class, "d")
            ->groupBy("d.agencia", "d.estado")
            ->getQuery()
            ->getArrayResult();
        foreach ($filas as $s) {
            $estado = $s["estado"] instanceof EstadoDepositoAgencia ? $s["estado"]->value : (string) $s["estado"];
            $sumas[(int) $s["agencia"]][$estado] = $s;
        }

        return array_map(static function (Agencia $a) use ($sumas) {
            $q = static fn(int $c) => DatosBoleto::importe(new Money($c, new Currency($a->getMoneda())));
            $acreditado = $sumas[(int) $a->getId()][EstadoDepositoAgencia::ACREDITADO->value] ?? null;
            $pendiente = $sumas[(int) $a->getId()][EstadoDepositoAgencia::REGISTRADO->value] ?? null;

            return [
                "id" => $a->getId(),
                "nombre" => $a->getNombre(),
                "activo" => $a->isActivo(),
                "moneda" => $a->getMoneda(),
                "porcentajeBonificacion" => $a->getPorcentajeBonificacion(),
                "saldo" => $q($a->getSaldo()),
                "totalDepositado" => $q((int) ($acreditado["importe"] ?? 0)),
                "totalBonificado" => $q((int) ($acreditado["bono"] ?? 0)),
                "pendientes" => (int) ($pendiente["cantidad"] ?? 0),
                "importePendiente" => $q((int) ($pendiente["importe"] ?? 0)),
            ];
        }, $agencias);
    }

    /** Agencias que se pueden elegir al registrar o filtrar. */
    public function agencias(?int $propia): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select("a.id", "a.nombre", "a.moneda", "a.porcentajeBonificacion", "a.activo")
            ->from(Agencia::class, "a")
            ->orderBy("a.nombre", "ASC");
        if ($propia !== null) {
            $qb->andWhere("a.id = :propia")->setParameter("propia", $propia);
        }

        return $qb->getQuery()->getArrayResult();
    }

    private static function limitar(QueryBuilder $qb, ?int $propia): void
    {
        if ($propia !== null) {
            $qb->andWhere("IDENTITY(d.agencia) = :propia")->setParameter("propia", $propia);
        }
    }
}
