<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
  shortName: 'ConfigVersions',
  normalizationContext: ['groups' => ['read:versions']],
  operations: [
    new GetCollection(
      uriTemplate: '/config-versions',
      paginationEnabled: false,
      provider: ConfigVersionsProvider::class,
    ),
  ],
)]
final class ConfigVersions {
  /** @var array<string, string> entityClass → updatedAt ISO8601 */
  #[Groups(['read:versions'])]
  public array $versions = [];



  /**
   * @param array<string, string> $versions
   */
  public function __construct(array $versions = []) {
    $this->versions = $versions;
  }
}
