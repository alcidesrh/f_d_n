<?php

namespace App\Services;

use App\Entity\Configuration\CollectionFieldConfig;
use App\Entity\Configuration\EntityConfiguration;
use App\Entity\Configuration\FormFieldConfig;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Psr\Log\LoggerInterface;

final class EntityConfigSynchronizer {
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly LoggerInterface $logger,
    private readonly ConfigChangePublisher $configChangePublisher,
  ) {
  }

  public function syncEntity(string $entityClass): void {
    $config = $this->entityManager->getRepository(EntityConfiguration::class)
      ->findOneBy(['entityClass' => $entityClass]);
    // if ($config) {
    //   $item = $config->getCollectionFieldConfig();
    //   $toDelete = $item->filter(fn(CollectionFieldConfig $v) => in_array($v->field, ['legacyId', 'password', 'apiTokens', 'plainPassword', 'userIdentifier']));
    //   foreach ($toDelete  as $key => $value) {
    //     $item->removeElement($value);
    //   }
    //   $this->entityManager->flush();
    // }
    // return;
    if (!$config) {
      $config = new EntityConfiguration($entityClass);
      $this->entityManager->persist($config);
      $this->logger->info('Configuración inicial creada para {entity}', ['entity' => $entityClass]);
    }

    $metadata = $this->entityManager->getClassMetadata('App\\Entity\\' . $entityClass);
    $currentFields = $this->getAllFieldNames($metadata);

    $this->syncCollectionFieldConfig($config, $currentFields);
    $this->syncFormFields($config, $currentFields);

    $this->entityManager->flush();
    $this->configChangePublisher->entityConfigChanged($config);
    $this->logger->debug('Sincronización completada para {entity}', ['entity' => $entityClass]);
  }

  public function getAllFieldNames(ClassMetadata $metadata): array {

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
      $detalle[] = [$campo, match ($metadata->getFieldMapping($campo)['type']) {
        'string', 'text'  => 'text',
        'integer', 'float' => 'number',
        default => $metadata->getFieldMapping($campo)['type'],
      }];
    }

    foreach ($relaciones as $relacion) {
      $temp = $metadata->getAssociationTargetClass($relacion);
      $detalle[] = [
        $relacion,
        match ($metadata->getAssociationMapping($relacion)['type']) {
          \Doctrine\ORM\Mapping\ClassMetadata::ONE_TO_ONE   => 'select', //'OneToOne',
          \Doctrine\ORM\Mapping\ClassMetadata::MANY_TO_ONE  => 'select', //'ManyToOne',
          \Doctrine\ORM\Mapping\ClassMetadata::ONE_TO_MANY  => 'multiple', //OneToMany',
          \Doctrine\ORM\Mapping\ClassMetadata::MANY_TO_MANY => 'multiple', //'ManyToMany',
          default => 'Desconocido',
        },
        substr($temp, strrpos($temp, '\\') + 1)
      ];
    }
    return $detalle;
  }


  private function syncCollectionFieldConfig(EntityConfiguration $config, array $currentFields): void {
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
      $this->logger->info('Campo de listado añadido automáticamente: {field} en {entity}', [
        'field' => $data[0],
        'entity' => $config->getEntityClass()
      ]);
    }
    $config->orderFields($config->getCollectionFieldConfig());
  }

  private function syncFormFields(EntityConfiguration $config, array $currentFields): void {
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

      $this->logger->info('Campo de formulario añadido automáticamente: {field} en {entity}', [
        'field' => $data[0],
        'entity' => $config->getEntityClass()
      ]);
    }
    $config->orderFields($config->getFormFields());
  }
}
