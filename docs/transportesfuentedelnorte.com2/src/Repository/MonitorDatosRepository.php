<?php

namespace App\Repository;

use App\Entity\MonitorDatos;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MonitorDatos>
 *
 * @method MonitorDatos|null find($id, $lockMode = null, $lockVersion = null)
 * @method MonitorDatos|null findOneBy(array $criteria, array $orderBy = null)
 * @method MonitorDatos[]    findAll()
 * @method MonitorDatos[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MonitorDatosRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, MonitorDatos::class);
    }

    //    /**
    //     * @return MonitorDatos[] Returns an array of MonitorDatos objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('m.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?MonitorDatos
    //    {
    //        return $this->createQueryBuilder('m')
    //            ->andWhere('m.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
