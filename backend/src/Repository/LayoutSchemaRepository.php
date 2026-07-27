<?php

namespace App\Repository;

use App\Entity\LayoutSchema;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LayoutSchema>
 */
class LayoutSchemaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LayoutSchema::class);
    }
}
