<?php

namespace App\Repository;

use App\Entity\ClienteReservacion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClienteReservacion>
 *
 * @method ClienteReservacion|null find($id, $lockMode = null, $lockVersion = null)
 * @method ClienteReservacion|null findOneBy(array $criteria, array $orderBy = null)
 * @method ClienteReservacion[]    findAll()
 * @method ClienteReservacion[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ClienteReservacionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClienteReservacion::class);
    }

    public function add(ClienteReservacion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ClienteReservacion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return ClienteReservacion[] Returns an array of ClienteReservacion objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ClienteReservacion
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
