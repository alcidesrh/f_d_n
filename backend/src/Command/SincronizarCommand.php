<?php

namespace App\Command;

use App\Migration\Migrador;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:sincronizar', description: 'Sincroniza boletos nuevos del sistema FDN')]
class SincronizarCommand extends Command
{
    public function __construct(
        private Migrador $migrador,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Sincronizando boletos nuevos...</info>');

        $start = microtime(true);

        try {
            $contadores = $this->migrador->migrarBoletosRecientes(output: $output);
        } catch (\Throwable $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");

            return Command::FAILURE;
        }

        $elapsed = microtime(true) - $start;
        $total = array_sum($contadores);

        if ($total > 0) {
            $table = new Table($output);
            $table->setHeaders(['Entidad', 'Cantidad']);
            foreach ($contadores as $entity => $count) {
                if ($count > 0) {
                    $table->addRow([$entity, $count]);
                }
            }
            $table->render();
        }

        $output->writeln(sprintf('<info>Registros sincronizados: %d (%.2f segundos)</info>', $total, $elapsed));

        return Command::SUCCESS;
    }
}
