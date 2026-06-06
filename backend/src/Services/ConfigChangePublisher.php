<?php

declare(strict_types=1);

namespace App\Services;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class ConfigChangePublisher {
  public function __construct(
    private readonly HubInterface $hub
  ) {
  }

  public function entityConfigChanged(string $entityClass): void {
    try {
      $this->hub->publish(new Update(
        'entity_configuration',
        json_encode(['entityClass' => $entityClass, 'action' => 'updated'])
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
