<?php

namespace App\Repository;

use App\Entity\Estacion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Estacion>
 *
 * @method Estacion|null find($id, $lockMode = null, $lockVersion = null)
 * @method Estacion|null findOneBy(array $criteria, array $orderBy = null)
 * @method Estacion[]    findAll()
 * @method Estacion[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EstacionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Estacion::class);
    }

    public function add(Estacion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Estacion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    //    /**
    //     * @return QueryBuilder object
    //     */
    public function getEstacionesSelect()
    {
        return $this->createQueryBuilder('e')
            ->select('e, d')
            ->leftJoin('e.departamento', 'd')
            ->where('e.activa = :true and d.activo = :true')
            ->orderBy('d.prioridad', 'desc')
            ->addOrderBy('e.prioridad', 'desc')
            ->setParameter('true', true);
    }

    //    public function findOneBySomeField($value): ?Estacion
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
