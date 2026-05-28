<?php

namespace App\Command;

use App\Migration\Migrador;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:migrar', description: 'Migra boletos del sistema FDN al nuevo sistema')]
class MigrarCommand extends Command
{
    public function __construct(
        private Migrador $migrador,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('cantidad', InputArgument::OPTIONAL, 'Número de boletos a migrar', '100')
            ->addOption('limpiar', null, InputOption::VALUE_NONE, 'Limpia la base de datos nueva antes de migrar');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cantidad = (int) $input->getArgument('cantidad');
        $limpiar = (bool) $input->getOption('limpiar');

        $output->writeln(sprintf(
            '<info>Migrando hasta %d boletos%s...</info>',
            $cantidad,
            $limpiar ? ' (limpiando primero)' : ''
        ));

        $start = microtime(true);

        try {
            $contadores = $this->migrador->migrarBoletos($cantidad, $limpiar);
        } catch (\Throwable $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");

            return Command::FAILURE;
        }

        $elapsed = microtime(true) - $start;

        $table = new Table($output);
        $table->setHeaders(['Entidad', 'Cantidad']);
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
