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
class EstacionRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Estacion::class);
    }
}
