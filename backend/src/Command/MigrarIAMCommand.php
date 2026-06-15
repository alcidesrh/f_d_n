<?php

namespace App\Command;

use App\Migration\MigradorIAM;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:migrar:iam', description: 'Migra roles, permisos y acciones del sistema FDN al nuevo sistema IAM')]
class MigrarIAMCommand extends Command {
    public function __construct(
        private MigradorIAM $migrador,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $output->writeln('<info>Migrando IAM (roles, permisos, acciones)...</info>');

        $start = microtime(true);

        try {
            $contadores = $this->migrador->migrar($output);
        } catch (\Throwable $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");

            return Command::FAILURE;
        }

        $elapsed = microtime(true) - $start;

        $table = new Table($output);
        $table->setHeaders(['Entidad', 'Registros']);
        foreach ($contadores as $entity => $count) {
            $table->addRow([$entity, $count]);
        }
        $table->render();

        $total = array_sum($contadores);
        $output->writeln('');
        $output->writeln("<info>Total registros insertados: {$total}</info>");
        $output->writeln(sprintf('<info>Tiempo: %.2f segundos</info>', $elapsed));

        return Command::SUCCESS;
    }
}
