<?php

namespace App\Command;

use App\Migration\Limpiador;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:reset-db',
    description: 'Reinicia la base de datos nueva: --hard dropea y recrea, --soft solo trunca tablas migrables'
)]
class ResetDbCommand extends Command {
    public function __construct(
        private Limpiador $limpiador,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->addOption('hard', null, InputOption::VALUE_NONE, 'Dropea la BD, la recrea y corre migraciones')
            ->addOption('soft', null, InputOption::VALUE_NONE, 'Solo trunca las tablas migrables (por defecto)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $hard = (bool) $input->getOption('hard');
        $soft = (bool) $input->getOption('soft') || !$hard;

        if ($hard) {
            $io->warning('HARD RESET: se dropeará y recreará la base de datos');
            if (!$io->confirm('¿Continuar?', false)) {
                return Command::SUCCESS;
            }

            $io->section('Dropeando base de datos...');
            $this->entityManager->getConnection()->executeStatement('DROP SCHEMA public CASCADE');
            $this->entityManager->getConnection()->executeStatement('CREATE SCHEMA public');
            $this->entityManager->getConnection()->executeStatement('GRANT ALL ON SCHEMA public TO PUBLIC');

            $io->section('Generando migraciones...');
            $p = new Process(['php', 'bin/console', 'doctrine:migrations:diff', '--no-interaction']);
            $p->setTimeout(120);
            $p->run(fn($type, $buf) => $io->write($buf));

            $io->section('Ejecutando migraciones...');
            $p = new Process(['php', 'bin/console', 'doctrine:migrations:migrate', '--no-interaction']);
            $p->setTimeout(120);
            $p->run(fn($type, $buf) => $io->write($buf));

            $io->success('Base de datos recreada y migraciones ejecutadas');
        }

        if ($soft) {
            $io->section('Truncando tablas migrables...');
            $this->limpiador->limpiar();
            $io->success('Tablas migrables truncadas');
        }

        return Command::SUCCESS;
    }
}
