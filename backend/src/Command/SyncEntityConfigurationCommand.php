<?php

namespace App\Command;

use App\Services\EntityConfigSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
  description: 'Sincroniza la configuración dinámica de entidades: crea la inicial, añade los campos nuevos de Doctrine y quita los que ya no existen.'
)]
class SyncEntityConfigurationCommand extends Command
{
  public function __construct(
    private readonly EntityConfigSynchronizer $synchronizer,
    private readonly EntityManagerInterface $entityManager,
  ) {
    parent::__construct();
  }

  protected function configure(): void
  {
    $this
      ->addArgument('entity', InputArgument::OPTIONAL, 'Clase de entidad específica a sincronizar (ej: Cliente)');
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $io = new SymfonyStyle($input, $output);
    $entityClass = $input->getArgument('entity');

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

  private function getAllEntityClasses(): array
  {
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
}
