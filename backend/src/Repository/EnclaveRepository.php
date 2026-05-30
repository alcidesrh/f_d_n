<?php

namespace App\Repository;

use App\Entity\Enclave;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends CustomEntityRepository<Enclave>
 *
 * @method Enclave|null find($id, $lockMode = null, $lockVersion = null)
 * @method Enclave|null findOneBy(array $criteria, array $orderBy = null)
 * @method Enclave[]    findAll()
 * @method Enclave[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EnclaveRepository extends CustomEntityRepository {
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Enclave::class);
    }

    //    /**
    //     * @return Enclave[] Returns an array of Enclave objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Enclave
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
