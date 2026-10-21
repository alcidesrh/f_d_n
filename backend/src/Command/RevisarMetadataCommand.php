<?php

declare(strict_types=1);

namespace App\Command;

use App\Metadata\PublicacionMetadata;
use App\Services\EntityConfigSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Al desplegar (lo corre el entrypoint del contenedor `backend`): con
 * `--sincronizar` pone la configuración de entidades al día con el mapeo de
 * Doctrine y luego publica una revisión de la metadata del cliente si cambió
 * el schema GraphQL o (modo automático) la configuración. Sin cambios no
 * publica nada: los navegadores siguen con lo que tienen (ADR-036).
 */
#[AsCommand(
    name: "app:metadata:revisar",
    description: "Publica una revisión de la metadata del cliente si cambió el schema GraphQL o la configuración de entidades.",
)]
final class RevisarMetadataCommand extends Command
{
    public function __construct(
        private readonly EntityConfigSynchronizer $sincronizador,
        private readonly PublicacionMetadata $publicacion,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption("sincronizar", null, InputOption::VALUE_NONE, "Antes, sincroniza la configuración de entidades con el mapeo de Doctrine (app:config:sync-metadata)");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($input->getOption("sincronizar")) {
            foreach ($this->sincronizador->entidades() as $entidad) {
                try {
                    $this->sincronizador->syncEntity($entidad);
                } catch (\Throwable $e) {
                    // Una entidad que no se deja sincronizar no frena a las demás ni la revisión.
                    $io->warning(sprintf("No se pudo sincronizar la configuración de %s: %s", $entidad, $e->getMessage()));
                }
            }
        }

        $publicada = $this->publicacion->revisarDespliegue();
        if ($publicada === null) {
            $io->writeln(sprintf("Metadata del cliente sin cambios (revisión %d, modo %s).", $this->publicacion->vigente()->revision, $this->publicacion->modo()->value));

            return Command::SUCCESS;
        }
        [$estado, $cambios] = $publicada;
        $detalle = array_filter([
            $cambios->esquema ? "schema GraphQL" : null,
            $cambios->agregadas !== [] ? "agregadas: " . implode(", ", $cambios->agregadas) : null,
            $cambios->modificadas !== [] ? "modificadas: " . implode(", ", $cambios->modificadas) : null,
            $cambios->eliminadas !== [] ? "eliminadas: " . implode(", ", $cambios->eliminadas) : null,
        ]);
        $io->success(sprintf("Revisión %d de la metadata publicada (%s).", $estado->revision, $detalle === [] ? "punto de partida" : implode("; ", $detalle)));

        return Command::SUCCESS;
    }
}
