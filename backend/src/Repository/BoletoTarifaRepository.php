<?php

namespace App\Repository;

use App\Entity\BoletoTarifa;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BoletoTarifa>
 */
class BoletoTarifaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BoletoTarifa::class);
    }
}
