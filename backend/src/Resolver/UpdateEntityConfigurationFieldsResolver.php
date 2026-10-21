<?php

namespace App\Resolver;

use ApiPlatform\GraphQl\Resolver\MutationResolverInterface;
use ApiPlatform\Metadata\IriConverterInterface;
use App\Entity\EntityConfiguration;
use App\Entity\collectionFieldConfigConfig;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Guarda la configuración de una entidad desde el editor. Los navegadores se
 * enteran por la revisión de la metadata que se publica al terminar la
 * petición (ADR-036).
 */
final class UpdateEntityConfigurationFieldsResolver implements MutationResolverInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private IriConverterInterface $iriConverter,
    ) {}

    public function __invoke($item, array $context): EntityConfiguration
    {
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
        if (\array_key_exists('listOptions', $args)) {
            $entity->setListOptions($args['listOptions']);
        }
        $entity->markAsUpdated();
        $this->entityManager->flush();

        return $entity;
    }

    private function extractId(string|int $iriOrId): int|string
    {
        if (is_string($iriOrId) && str_starts_with($iriOrId, '/')) {
            $parts = explode('/', rtrim($iriOrId, '/'));
            return end($parts);
        }
        return $iriOrId;
    }
}
