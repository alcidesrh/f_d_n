<?php

declare(strict_types=1);

namespace App\Command;

use App\Acceso\Legado\ConsolidadorRoles;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Deja los roles del legado en puestos (ADR-052). Sin `--aplicar` hace todo
 * dentro de una transacción y la deshace: dice lo que pasaría, con la misma
 * comprobación de que nadie cambia lo que puede.
 */
#[AsCommand(
    name: 'app:acceso:consolidar-roles',
    description: 'Fusiona los roles del legado en puestos y quita las asignaciones que sobran (ADR-052).',
)]
final class ConsolidarRolesCommand extends Command
{
    public function __construct(
        private readonly ConsolidadorRoles $consolidador,
        private readonly Connection $db,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('aplicar', null, InputOption::VALUE_NONE, 'Guarda los cambios (sin esto, solo informa)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $aplicar = (bool) $input->getOption('aplicar');
        $this->db->beginTransaction();
        try {
            $r = $this->consolidador->consolidar();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $output->writeln(sprintf('<error>%s</error> No se guardó nada.', $e->getMessage()));

            return Command::FAILURE;
        }
        $aplicar ? $this->db->commit() : $this->db->rollBack();

        $output->writeln(sprintf(
            '<info>%s</info> %d roles fusionados, %d eliminados y %d asignaciones quitadas. Nadie cambia lo que puede.',
            $aplicar ? 'Listo:' : 'Simulación:',
            $r['fusionados'],
            $r['eliminados'],
            $r['quitadas'],
        ));
        foreach (['sinFusionar' => 'Sin fusionar, sus capacidades o límites ya difieren', 'conservados' => 'Conservados, siguen en uso'] as $clave => $texto) {
            if ($r[$clave] !== []) {
                $output->writeln(sprintf('%s: %s', $texto, implode(', ', $r[$clave])));
            }
        }
        if ($r['estaciones'] !== []) {
            $output->writeln(sprintf('Cambian las estaciones de trabajo que pueden elegir: %s', implode(', ', $r['estaciones'])));
        }

        return Command::SUCCESS;
    }
}
