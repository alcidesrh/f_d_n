<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Enum\TipoCombustible;
use App\Entity\PrecioCombustible;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PrecioCombustible>
 *
 * @method PrecioCombustible|null find($id, $lockMode = null, $lockVersion = null)
 * @method PrecioCombustible|null findOneBy(array $criteria, array $orderBy = null)
 * @method PrecioCombustible[]    findAll()
 * @method PrecioCombustible[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PrecioCombustibleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrecioCombustible::class);
    }

    /**
     * El precio del tipo que rige ese día: el del mismo día o, si no hay, el
     * último anterior. Nulo si todavía no hay ninguno (ADR-073).
     */
    public function vigente(\DateTimeInterface $dia, TipoCombustible $tipo): ?PrecioCombustible
    {
        return $this->createQueryBuilder("p")
            ->andWhere("p.tipo = :tipo")
            ->andWhere("p.fecha <= :dia")
            ->setParameter("tipo", $tipo)
            ->setParameter("dia", $dia->format("Y-m-d"))
            ->orderBy("p.fecha", "DESC")
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
