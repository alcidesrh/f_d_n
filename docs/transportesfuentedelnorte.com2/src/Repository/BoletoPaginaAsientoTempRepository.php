<?php

namespace App\Repository;

use App\EntitySistemaFdn\BoletoPaginaAsientoTemp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BoletoPaginaAsientoTemp>
 *
 * @method BoletoPaginaAsientoTemp|null find($id, $lockMode = null, $lockVersion = null)
 * @method BoletoPaginaAsientoTemp|null findOneBy(array $criteria, array $orderBy = null)
 * @method BoletoPaginaAsientoTemp[]    findAll()
 * @method BoletoPaginaAsientoTemp[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BoletoPaginaAsientoTempRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BoletoPaginaAsientoTemp::class);
    }

    public function save(BoletoPaginaAsientoTemp $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(BoletoPaginaAsientoTemp $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    //    /**
    //     * @return BoletoPaginaAsientoTemp[] Returns an array of BoletoPaginaAsientoTemp objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('b.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?BoletoPaginaAsientoTemp
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
