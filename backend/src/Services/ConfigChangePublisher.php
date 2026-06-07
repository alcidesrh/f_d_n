<?php

declare(strict_types=1);

namespace App\Services;

use App\Entity\Configuration\EntityConfiguration;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class ConfigChangePublisher {
  public function __construct(
    private readonly HubInterface $hub
  ) {
  }

  public function entityConfigChanged(EntityConfiguration $entityClass): void {
    try {
      $this->hub->publish(new Update(
        'entity_configuration',
        json_encode(['entityClass' => $entityClass->getEntityClass(), 'action' => 'updated', 'updatedAt' => $entityClass->getUpdatedAt()->format('c')])
      ));
    } catch (\Exception) {
    }
  }

  public function graphqlSchemaChanged(): void {
    try {
      $this->hub->publish(new Update(
        'graphql_schema',
        json_encode(['action' => 'changed'])
      ));
    } catch (\Exception) {
    }
  }
}
