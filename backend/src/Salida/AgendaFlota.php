<?php

declare(strict_types=1);

namespace App\Salida;

use App\Entity\Enum\EstadoSalida;
use App\Entity\Salida;
use Doctrine\ORM\EntityManagerInterface;

/** Arma la `AgendaBus` de unos buses con sus salidas vigentes (no canceladas) en un rango. */
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
            ->select("s", "t", "o", "d", "b")
            ->from(Salida::class, "s")
            ->join("s.trayecto", "t")
            ->join("t.origen", "o")
            ->join("t.destino", "d")
            ->join("s.bus", "b")
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
            $agenda->ocupar((int) $s->getBus()->getId(), $s->getFecha(), $s->getTrayecto()->getDuracionEstimadaMinutos(), VistaSalida::referencia($s) + ["trayectoId" => $s->getTrayecto()->getId()]);
        }

        return $agenda;
    }
}
