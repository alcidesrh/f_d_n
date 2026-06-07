<?php

namespace App\Resolver;

use ApiPlatform\GraphQl\Resolver\MutationResolverInterface;
use ApiPlatform\Metadata\IriConverterInterface;
use App\Entity\Configuration\EntityConfiguration;
use App\Entity\Configuration\collectionFieldConfigConfig;
use App\Services\ConfigChangePublisher;
use Doctrine\ORM\EntityManagerInterface;

final class UpdateEntityConfigurationFieldsResolver implements MutationResolverInterface {
  public function __construct(
    private EntityManagerInterface $entityManager,
    private IriConverterInterface $iriConverter,
    private ConfigChangePublisher $configChangePublisher,
  ) {
  }

  public function __invoke($item, array $context): EntityConfiguration {
    $args = $context['args']['input'];

    // Cargar la entidad (acepta IRI o ID numérico)
    // $id = $this->extractId($args['id']);
    $entity = $this->entityManager->getRepository(EntityConfiguration::class)->findOneBy(['entityClass' => $args['entityClass']]);

    if (!$entity) {
      throw new \RuntimeException('EntityConfiguration no encontrada');
    }

    // Procesar formFieldConfigs (JSON string → array)
    if (isset($args['formFields'])) {
      foreach ($args['formFields'] as $data) {
        $field = $this->iriConverter->getResourceFromIri($data['id']);
        $field->loadData($data);
      }
    }
    if (isset($args['collectionFieldConfig'])) {
      foreach ($args['collectionFieldConfig'] as $data) {
        $field = $this->iriConverter->getResourceFromIri($data['id']);
        $field->loadData($data);
      }
    }
    $entity->markAsUpdated();
    $this->entityManager->flush();

    $this->configChangePublisher->entityConfigChanged($entity);

    return $entity;
  }

  private function extractId(string|int $iriOrId): int|string {
    if (is_string($iriOrId) && str_starts_with($iriOrId, '/')) {
      $parts = explode('/', rtrim($iriOrId, '/'));
      return end($parts);
    }
    return $iriOrId;
  }
}
