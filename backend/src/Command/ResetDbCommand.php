<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'reset-db',
    description: 'Add a short description for your command',
)]
class ResetDbCommand extends Command {
    public function __construct() {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);
        $arg1 = $input->getArgument('arg1');

        if ($arg1) {
            $io->note(sprintf('You passed an argument: %s', $arg1));
        }

        if ($input->getOption('option1')) {
            // ...
        }

        try {
            $io = new SymfonyStyle($input, $output);
            // $phpBinaryFinder = new PhpExecutableFinder();
            // $phpBinaryPath = $phpBinaryFinder->find();

            // $process = [
            // [$phpBinaryPath, 'bin/console', 'doctrine:database:drop', '-nf', '--if-exists', '--quiet'],
            // [$phpBinaryPath, 'bin/console', 'doctrine:database:create', '-n', '--quiet'],
            // ['rm', '-r', 'migrations/*'],
            // [$phpBinaryPath, 'bin/console', 'doctrine:migrations:diff', '-n', '--quiet'],
            // [$phpBinaryPath, 'bin/console', 'doctrine:migrations:migrate', '-n', '--quiet'],
            // ];
            // $io->progressStart(100);
            // foreach ($process as $key => $value) {
            // $io->progressAdvance(20);
            // ($p = new Process($value))->run();
            // $io->block($p->getOutput());
            // }
            $p1 = Process::fromShellCommandline('docker exec backend php bin/console doctrine:database:drop -nf --if-exists --quiet');
            $p2 = Process::fromShellCommandline('docker exec backend php bin/console doctrine:database:create');
            $p3 = Process::fromShellCommandline('docker exec backend rm -r migrations/*');
            $p4 = Process::fromShellCommandline('docker exec backend php bin/console doctrine:migrations:diff');
            // $p5 = Process::fromShellCommandline('docker exec backend php bin/console doctrine:migrations:migrate');
            $p6 = Process::fromShellCommandline('docker exec backend php bin/console migrar');
            $p7 = Process::fromShellCommandline('docker exec backend php bin/console migrar:boletos 100');
            foreach ([$p1, $p2, $p3, $p4, $p6, $p7,] as $key => $value) {
                $value->setTimeout(120);
                $value->run();
            }
            // $p->run();
            $io->success('Base de datos generada!');
        } catch (\Throwable $th) {
            throw $th;
        }
        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }
}
