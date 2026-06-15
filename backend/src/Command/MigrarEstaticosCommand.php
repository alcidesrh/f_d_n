<?php

namespace App\Command;

use App\Migration\MigradorEstaticos;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:migrar:estaticos', description: 'Migra datos estáticos (empresa, estación, bus, asiento, cliente, usuario, trayecto, tarifa) del sistema FDN al nuevo')]
class MigrarEstaticosCommand extends Command {
    public function __construct(
        private MigradorEstaticos $migrador,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $output->writeln('<info>Migrando datos estáticos...</info>');

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
