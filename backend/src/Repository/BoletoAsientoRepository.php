<?php

namespace App\Repository;

use App\Entity\BoletoAsiento;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BoletoAsiento>
 */
class BoletoAsientoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BoletoAsiento::class);
    }
}
