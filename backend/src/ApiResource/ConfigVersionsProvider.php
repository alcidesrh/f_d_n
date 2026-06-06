<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Configuration\EntityConfiguration;
use Doctrine\ORM\EntityManagerInterface;

final class ConfigVersionsProvider implements ProviderInterface {
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
  ) {
  }

  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null {
    $repository = $this->entityManager->getRepository(EntityConfiguration::class);
    $configs = $repository->findAll();

    $versions = [];
    foreach ($configs as $config) {
      $updatedAt = $config->getUpdatedAt();
      $versions[$config->getEntityClass()] = $updatedAt ? $updatedAt->format('c') : '';
    }

    return [new ConfigVersions($versions)];
  }
}
