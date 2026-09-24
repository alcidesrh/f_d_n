<?php

namespace App\Services;

use App\Entity\CollectionFieldConfig;
use App\Entity\EntityConfiguration;
use App\Entity\FormFieldConfig;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Psr\Log\LoggerInterface;

final class EntityConfigSynchronizer
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly ConfigChangePublisher $configChangePublisher,
    ) {}

    public function syncEntity(string $entityClass, $publish = true): EntityConfiguration
    {
        $config = $this->entityManager->getRepository(EntityConfiguration::class)
            ->findOneBy(['entityClass' => $entityClass]);
        if (!$config) {
            $config = new EntityConfiguration($entityClass);
            $this->entityManager->persist($config);
        }

        $metadata = $this->entityManager->getClassMetadata('App\\Entity\\' . $entityClass);
        $currentFields = self::getAllFieldNames($metadata);

        $this->syncCollectionFieldConfig($config, $currentFields);
        $this->syncFormFields($config, $currentFields);

        $this->entityManager->flush();
        if ($publish) {
            $this->configChangePublisher->entityConfigChanged($config);
        }
        return $config;
    }

    public static function getAllFieldNames(ClassMetadata $metadata): array
    {

        // Campos simples (columnas en la tabla user)
        $camposSimples = $metadata->getFieldNames();
        // Ejemplo típico: ['id', 'email', 'username', 'createdAt', 'isActive']

        // Relaciones (propiedades que son entidades o colecciones)
        $relaciones = $metadata->getAssociationNames();
        // Ejemplo típico: ['profile', 'roles', 'posts', 'address', 'favoriteProducts']

        // Combinado: todos los "atributos mapeados"
        $todosLosNombres = array_merge($camposSimples, $relaciones);

        // Para mayor detalle puedes hacer:
        $detalle = [];
        foreach ($camposSimples as $campo) {
            if (\in_array($campo, ['legacyId', 'password', 'apiTokens'])) {
                continue;
            }
            $mapping = $metadata->getFieldMapping($campo);
            $detalle[] = [$campo, match ($mapping->type) {
                'string', 'text'  => 'text',
                'integer', 'float' => 'number',
                default => $mapping->type,
            }];
        }

        foreach ($relaciones as $relacion) {
            $targetClass = $metadata->getAssociationTargetClass($relacion);
            $assocMapping = $metadata->getAssociationMapping($relacion);
            $detalle[] = [
                $relacion,
                match ($assocMapping->type()) {
                    \Doctrine\ORM\Mapping\ClassMetadata::ONE_TO_ONE   => 'select',
                    \Doctrine\ORM\Mapping\ClassMetadata::MANY_TO_ONE  => 'select',
                    \Doctrine\ORM\Mapping\ClassMetadata::ONE_TO_MANY  => 'multiple',
                    \Doctrine\ORM\Mapping\ClassMetadata::MANY_TO_MANY => 'multiple',
                    default => 'Desconocido',
                },
                substr($targetClass, strrpos($targetClass, '\\') + 1)
            ];
        }
        return $detalle;
    }


    private function syncCollectionFieldConfig(EntityConfiguration $config, array $currentFields): void
    {
        $existing = [];
        foreach ($config->getCollectionFieldConfig() as $field) {
            $existing[$field->getField()] = $field;
        }

        foreach ($currentFields as $data) {
            if (!isset($existing[$data[0]])) {
                $collectionFieldConfig = new CollectionFieldConfig($data);
                $this->entityManager->persist($collectionFieldConfig);
            } else {
                $collectionFieldConfig = $existing[$data[0]];
                $collectionFieldConfig->setData($data);
            }
            $config->addcollectionFieldConfig($collectionFieldConfig);
        }
        $config->orderFields($config->getCollectionFieldConfig());
    }

    private function syncFormFields(EntityConfiguration $config, array $currentFields): void
    {
        $existing = [];
        foreach ($config->getFormFields() as $field) {
            $existing[$field->getField()] = $field;
        }

        foreach ($currentFields as $data) {

            if (!isset($existing[$data[0]])) {
                $formField = new FormFieldConfig($data);
                $this->entityManager->persist($formField);
            } else {
                $formField = $existing[$data[0]];
                $formField->setData($data);
            }

            $config->addFormField($formField);

        }
        $config->orderFields($config->getFormFields());
    }
}
