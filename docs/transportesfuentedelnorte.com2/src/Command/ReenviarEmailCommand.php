<?php

namespace App\Command;

use App\Services\RemoteDatabaseQueries;
use App\Services\ReservacionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'reenviar-email',
    description: 'Comando para anular reservaciones incompletas',
)]
class ReenviarEmailCommand extends Command {

    public function __construct(private EntityManagerInterface $entityManagerInterface, private ReservacionService $reservacionService, private  RemoteDatabaseQueries $remoteDatabaseQueries) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {


        $response = $this->reservacionService->reenviarEmail();
        return Command::SUCCESS;
    }
}
