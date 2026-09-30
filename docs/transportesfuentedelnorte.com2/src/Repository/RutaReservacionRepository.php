<?php

namespace App\Repository;

use App\Entity\RutaReservacion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RutaReservacion>
 *
 * @method RutaReservacion|null find($id, $lockMode = null, $lockVersion = null)
 * @method RutaReservacion|null findOneBy(array $criteria, array $orderBy = null)
 * @method RutaReservacion[]    findAll()
 * @method RutaReservacion[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RutaReservacionRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, RutaReservacion::class);
    }

    public function add(RutaReservacion $entity, bool $flush = false): void {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RutaReservacion $entity, bool $flush = false): void {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    //    /**
    //     * @return RutaReservacion[] Returns an array of RutaReservacion objects
    //     */
    public function findByLimit($first_result = 0, $max_result = 100): array {
        return $this->createQueryBuilder('r')
            //    ->andWhere('r.exampleField = :val')
            //    ->setParameter('val', $value)
            //    ->orderBy('r.id', 'ASC')
            ->setMaxResults($max_result)
            ->setFirstResult($first_result)
            ->getQuery()
            ->getResult();
    }

    //    public function findOneBySomeField($value): ?RutaReservacion
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
