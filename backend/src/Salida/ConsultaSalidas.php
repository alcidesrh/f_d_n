<?php

declare(strict_types=1);

namespace App\Salida;

use App\Acceso\Alcance;
use App\Acceso\FiltroAlcance;
use App\Entity\Asiento;
use App\Entity\Salida;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/** Listado paginado de salidas para la gestión logística (ADR-024). */
final class ConsultaSalidas
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AsientosVendidos $vendidos,
    ) {}

    /**
     * Con `$alcance`, solo las salidas de esas empresas (ADR-039).
     *
     * @return array<string, mixed>
     */
    public function buscar(FiltroSalidas $f, ?Alcance $alcance = null): array
    {
        $base = $this->base($f);
        if ($alcance !== null) {
            FiltroAlcance::aplicar($base, $alcance, "s.empresa", compartidos: true);
        }

        $porEstado = [];
        $conteo = (clone $base)->select("s.estado AS estado", "COUNT(s.id) AS n")->groupBy("s.estado")->getQuery()->getArrayResult();
        foreach ($conteo as $c) {
            $porEstado[$c["estado"] instanceof \BackedEnum ? $c["estado"]->value : (string) $c["estado"]] = (int) $c["n"];
        }

        $qb = (clone $base)->select("s", "r", "b", "e");
        if ($f->estados !== []) {
            $qb->andWhere("s.estado IN (:estados)")->setParameter("estados", array_map(static fn($e) => $e->value, $f->estados));
        }
        $total = (int) (clone $qb)->select("COUNT(s.id)")->getQuery()->getSingleScalarResult();

        if ($f->orden === FiltroSalidas::PROXIMAS) {
            $qb->addSelect("CASE WHEN s.estado = 'iniciada' THEN 0 WHEN s.estado = 'abordando' THEN 1 ELSE 2 END AS HIDDEN prioridad")
                ->orderBy("prioridad", "ASC")
                ->addOrderBy("s.fecha", "ASC");
        } else {
            $qb->orderBy("s.fecha", $f->direccion === "desc" ? "DESC" : "ASC");
        }
        /** @var list<Salida> $salidas */
        $salidas = $qb->addOrderBy("s.id", "ASC")
            ->setFirstResult(($f->pagina - 1) * $f->porPagina)
            ->setMaxResults($f->porPagina)
            ->getQuery()
            ->getResult();

        $vendidos = $this->vendidos->porSalida(array_map(static fn(Salida $s) => (int) $s->getId(), $salidas));
        $capacidad = $this->capacidad(array_values(array_unique(array_filter(array_map(static fn(Salida $s) => $s->getBus()?->getId(), $salidas)))));
        $ahora = new \DateTimeImmutable();

        return [
            "items" => array_map(
                static fn(Salida $s) => VistaSalida::fila($s, $vendidos[$s->getId()] ?? 0, $capacidad[$s->getBus()?->getId()] ?? null, $ahora),
                $salidas,
            ),
            "total" => $total,
            "pagina" => $f->pagina,
            "porPagina" => $f->porPagina,
            "porEstado" => $porEstado,
        ];
    }

    /** Todo el filtro salvo el estado (el conteo por estado lo ignora). */
    private function base(FiltroSalidas $f): QueryBuilder
    {
        $qb = $this->em->createQueryBuilder()
            ->from(Salida::class, "s")
            ->join("s.ruta", "r")
            ->leftJoin("s.bus", "b")
            ->leftJoin("s.empresa", "e");
        $iguales = ["s.empresa" => $f->empresa, "s.ruta" => $f->ruta, "s.bus" => $f->bus];
        $n = 0;
        foreach ($iguales as $campo => $valor) {
            if ($valor !== null) {
                $qb->andWhere("{$campo} = :p" . ++$n)->setParameter("p{$n}", $valor);
            }
        }
        // Origen y destino: las puntas de la ruta.
        if ($f->origen !== null) {
            $qb->andWhere("EXISTS (SELECT 1 FROM App\Entity\Parada po WHERE po.ruta = s.ruta AND po.orden = 0 AND po.enclave = :origen)")
                ->setParameter("origen", $f->origen);
        }
        if ($f->destino !== null) {
            $qb->andWhere("EXISTS (SELECT 1 FROM App\Entity\Parada pd WHERE pd.ruta = s.ruta AND pd.enclave = :destino
                AND pd.orden = (SELECT MAX(px.orden) FROM App\Entity\Parada px WHERE px.ruta = s.ruta))")
                ->setParameter("destino", $f->destino);
        }
        if ($f->desde !== null) {
            $qb->andWhere("s.fecha >= :desde")->setParameter("desde", $f->desde);
        }
        if ($f->hasta !== null) {
            $qb->andWhere("s.fecha < :hasta")->setParameter("hasta", $f->hasta->modify("+1 day"));
        }

        return $qb;
    }

    /**
     * @param list<int> $busIds
     *
     * @return array<int, int> asientos por bus
     */
    private function capacidad(array $busIds): array
    {
        if ($busIds === []) {
            return [];
        }
        $filas = $this->em->createQueryBuilder()
            ->select("IDENTITY(a.bus) AS bus", "COUNT(a.id) AS n")
            ->from(Asiento::class, "a")
            ->where("a.bus IN (:buses)")
            ->groupBy("a.bus")
            ->setParameter("buses", $busIds)
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn(array $f) => [(int) $f["bus"], (int) $f["n"]], $filas), 1, 0);
    }
}
