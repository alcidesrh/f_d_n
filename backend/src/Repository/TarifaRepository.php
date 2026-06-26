<?php

namespace App\Repository;

use App\Entity\Tarifa;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends CustomEntityRepository<Tarifa>
 *
 * @method Tarifa|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tarifa|null findOneBy(array $criteria, array $orderBy = null)
 * @method Tarifa[]    findAll()
 * @method Tarifa[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TarifaRepository extends CustomEntityRepository {
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Tarifa::class);
    }

    //    /**
    //     * @return Tarifa[] Returns an array of Tarifa objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Tarifa
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
