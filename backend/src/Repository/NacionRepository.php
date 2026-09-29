<?php

namespace App\Repository;

use App\Entity\Nacion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Nacion>
 *
 * @method Nacion|null find($id, $lockMode = null, $lockVersion = null)
 * @method Nacion|null findOneBy(array $criteria, array $orderBy = null)
 * @method Nacion[]    findAll()
 * @method Nacion[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NacionRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Nacion::class);
    }
}
