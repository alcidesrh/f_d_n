<?php

namespace App\Repository;

use App\Entity\Salida;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends CustomEntityRepository<Salida>
 *
 * @method Salida|null find($id, $lockMode = null, $lockVersion = null)
 * @method Salida|null findOneBy(array $criteria, array $orderBy = null)
 * @method Salida[]    findAll()
 * @method Salida[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SalidaRepository extends CustomEntityRepository {
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Salida::class);
    }

    //    /**
    //     * @return Salida[] Returns an array of Salida objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Salida
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
