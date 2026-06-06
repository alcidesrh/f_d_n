<?php

namespace App\Command;

use App\Entity\Configuration\CollectionFieldConfig;
use App\Entity\Configuration\EntityConfiguration;
use App\Entity\Configuration\FormFieldConfig;
use App\Services\EntityConfigSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

//Attention Uso del comando ****************************************************** */
// # Sincronizar TODAS las entidades
// php bin/console app:config:sync-metadata

// # Sincronizar solo una entidad
// php bin/console app:config:sync-metadata App\\Entity\\Cliente

// # Sincronizar y eliminar campos que ya no existen
// php bin/console app:config:sync-metadata --remove-obsolete
#[AsCommand(
  name: 'app:config:sync-metadata',
  description: 'Sincroniza la configuración dinámica de entidades: crea configuración inicial y añade campos nuevos detectados en Doctrine.'
)]
class SyncEntityConfigurationCommand extends Command {
  public function __construct(
    private readonly EntityConfigSynchronizer $synchronizer,
    private readonly EntityManagerInterface $entityManager,
    private readonly ManagerRegistry $registry
  ) {
    parent::__construct();
  }

  protected function configure(): void {
    $this
      ->addArgument('entity', InputArgument::OPTIONAL, 'Clase de entidad específica a sincronizar (ej: App\Entity\Cliente)')
      ->addOption('remove-obsolete', null, InputOption::VALUE_NONE, 'Eliminar campos que ya no existen en la entidad');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $io = new SymfonyStyle($input, $output);
    $entityClass = $input->getArgument('entity');
    $removeObsolete = $input->getOption('remove-obsolete');

    $classesToSync = $entityClass
      ? [$entityClass]
      : $this->getAllEntityClasses();

    $io->title('Sincronizando configuración de entidades');

    foreach ($classesToSync as $class) {
      $io->section("Procesando: {$class}");
      $this->synchronizer->syncEntity($class);  // ← solo esta línea
      $io->success("✓ {$class} sincronizada");
    }

    $io->newLine();
    $io->success('Sincronización completada');

    return Command::SUCCESS;
  }

  private function getAllEntityClasses(): array {
    $classes = [];
    $metadataFactory = $this->entityManager->getMetadataFactory();

    foreach ($metadataFactory->getAllMetadata() as $metadata) {
      if (!$metadata->isMappedSuperclass && !$metadata->isEmbeddedClass) {
        if (!in_array(($name = $metadata->getReflectionClass()->getShortName()), ['EntityConfiguration', 'CollectionFieldConfig', 'FormFieldConfig'], true)) {
          $classes[] = $name;
        }
      }
    }

    return $classes;
  }

  private function getAllFieldNames(ClassMetadata $metadata): array {
    $fields = array_merge(
      $metadata->getFieldNames(),
      $metadata->getAssociationNames()
    );

    // Orden alfabético para consistencia inicial
    sort($fields);

    return $fields;
  }

  private function synccollectionFieldConfig(EntityConfiguration $config, array $currentFields, bool $removeObsolete, SymfonyStyle $io): void {
    $existing = [];
    foreach ($config->getcollectionFieldConfig() as $field) {
      $existing[$field->getFieldName()] = $field;
    }

    $maxPosition = $existing ? max(array_map(fn($f) => $f->getPosition(), $existing)) : 0;

    foreach ($currentFields as $fieldName) {
      if (!isset($existing[$fieldName])) {
        $collectionFieldConfig = new CollectionFieldConfig();
        $collectionFieldConfig->setFieldName($fieldName);
        $collectionFieldConfig->setPosition(++$maxPosition);
        $collectionFieldConfig->setVisible(true);
        $collectionFieldConfig->setFilterable(false);
        $collectionFieldConfig->setLabel(null);

        $config->addcollectionFieldConfig($collectionFieldConfig);
        $this->entityManager->persist($collectionFieldConfig);

        $io->text("  • Añadido campo listado: <comment>{$fieldName}</comment>");
      }
    }

    if ($removeObsolete) {
      foreach ($existing as $fieldName => $field) {
        if (!in_array($fieldName, $currentFields, true)) {
          $config->removecollectionFieldConfig($field);
          $this->entityManager->remove($field);
          $io->text("  • Eliminado campo obsoleto (listado): <comment>{$fieldName}</comment>");
        }
      }
    }
  }

  private function syncFormFields(EntityConfiguration $config, array $currentFields, bool $removeObsolete, SymfonyStyle $io): void {
    $existing = [];
    foreach ($config->getFormFields() as $field) {
      $existing[$field->getFieldName()] = $field;
    }

    $maxPosition = $existing ? max(array_map(fn($f) => $f->getPosition(), $existing)) : 0;

    foreach ($currentFields as $fieldName) {
      if (!isset($existing[$fieldName])) {
        $formField = new FormFieldConfig();
        $formField->setFieldName($fieldName);
        $formField->setPosition(++$maxPosition);
        $formField->setGroupName(null);
        $formField->setInputProps(null);

        $config->addFormField($formField);
        $this->entityManager->persist($formField);

        $io->text("  • Añadido campo formulario: <comment>{$fieldName}</comment>");
      }
    }

    if ($removeObsolete) {
      foreach ($existing as $fieldName => $field) {
        if (!in_array($fieldName, $currentFields, true)) {
          $config->removeFormField($field);
          $this->entityManager->remove($field);
          $io->text("  • Eliminado campo obsoleto (formulario): <comment>{$fieldName}</comment>");
        }
      }
    }
  }
}
