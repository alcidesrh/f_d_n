<?php

namespace App\Repository;

use App\Entity\Configuration\EntityConfiguration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EntityConfigurationRepository extends ServiceEntityRepository {
  public function __construct(ManagerRegistry $registry) {
    parent::__construct($registry, EntityConfiguration::class);
  }

  public function findOneByEntityClass(string $entityClass): ?EntityConfiguration {
    return $this->findOneBy(['entityClass' => $entityClass]);
  }
}
