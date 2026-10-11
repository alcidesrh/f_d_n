<?php

declare(strict_types=1);

namespace App\Salida;

use App\Entity\Enum\EstadoSalida;
use App\Entity\Salida;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Arma la `AgendaBus` de unos buses con sus salidas vigentes (no canceladas)
 * en un rango; cada salida dura lo que su ruta con la clase de su bus.
 */
final class AgendaFlota
{
    /** Margen antes del rango: una salida de la víspera puede seguir en ruta. */
    private const MARGEN = "-2 days";

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * @param list<int> $busIds
     * @param list<int> $excluir salidas que no cuentan (las que se están moviendo)
     */
    public function de(array $busIds, \DateTimeImmutable $desde, \DateTimeImmutable $hasta, array $excluir = []): AgendaBus
    {
        $agenda = new AgendaBus();
        if ($busIds === []) {
            return $agenda;
        }
        $qb = $this->em->createQueryBuilder()
            ->select("s", "r", "p", "e", "t", "b", "k")
            ->from(Salida::class, "s")
            ->join("s.ruta", "r")
            ->join("r.paradas", "p")
            ->join("p.enclave", "e")
            ->leftJoin("p.tiempos", "t")
            ->join("s.bus", "b")
            ->leftJoin("b.clase", "k")
            ->where("b.id IN (:buses)")
            ->andWhere("s.estado != :cancelada")
            ->andWhere("s.fecha >= :desde AND s.fecha < :hasta")
            ->setParameter("buses", $busIds)
            ->setParameter("cancelada", EstadoSalida::CANCELADA->value)
            ->setParameter("desde", $desde->modify(self::MARGEN))
            ->setParameter("hasta", $hasta);
        if ($excluir !== []) {
            $qb->andWhere("s.id NOT IN (:excluir)")->setParameter("excluir", $excluir);
        }
        /** @var list<Salida> $salidas */
        $salidas = $qb->getQuery()->getResult();
        foreach ($salidas as $s) {
            $agenda->ocupar((int) $s->getBus()->getId(), $s->getFecha(), $s->getRuta()->duracionPara($s->getBus()->getClase()), VistaSalida::referencia($s) + ["rutaId" => $s->getRuta()->getId()]);
        }

        return $agenda;
    }
}
