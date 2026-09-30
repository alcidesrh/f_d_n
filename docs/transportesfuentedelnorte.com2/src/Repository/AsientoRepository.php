<?php

namespace App\Repository;

use App\Entity\Asiento;
use App\Entity\Reservacion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Asiento>
 *
 * @method Asiento|null find($id, $lockMode = null, $lockVersion = null)
 * @method Asiento|null findOneBy(array $criteria, array $orderBy = null)
 * @method Asiento[]    findAll()
 * @method Asiento[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AsientoRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Asiento::class);
    }

    public function add(Asiento $entity, bool $flush = false): void {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Asiento $entity, bool $flush = false): void {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Asiento[] Returns an array of Asiento objects
     */
    public function getAsientosPorSalida($value): array {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.salidaReservacion', 's')
            ->andWhere('a.asiento_id in (:asientos) and s.salida_id = :salida_id')
            ->setParameters($value)
            ->getQuery()
            ->getResult();
    }

    //    public function findOneBySomeField($value): ?Asiento
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
