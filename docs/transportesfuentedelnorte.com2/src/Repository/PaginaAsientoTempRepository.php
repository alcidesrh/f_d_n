<?php

namespace App\Repository;

use App\Entity\PaginaAsientoTemp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaginaAsientoTemp>
 *
 * @method PaginaAsientoTemp|null find($id, $lockMode = null, $lockVersion = null)
 * @method PaginaAsientoTemp|null findOneBy(array $criteria, array $orderBy = null)
 * @method PaginaAsientoTemp[]    findAll()
 * @method PaginaAsientoTemp[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PaginaAsientoTempRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaginaAsientoTemp::class);
    }

    public function save(PaginaAsientoTemp $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PaginaAsientoTemp $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return PaginaAsientoTemp[] Returns an array of PaginaAsientoTemp objects
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

//    public function findOneBySomeField($value): ?PaginaAsientoTemp
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
