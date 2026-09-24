<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use App\Entity\Permiso;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Permiso>
 *
 * @method Permiso|null find($id, $lockMode = null, $lockVersion = null)
 * @method Permiso|null findOneBy(array $criteria, array $orderBy = null)
 * @method Permiso[]    findAll()
 * @method Permiso[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PermisoRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Permiso::class);
    }
}
