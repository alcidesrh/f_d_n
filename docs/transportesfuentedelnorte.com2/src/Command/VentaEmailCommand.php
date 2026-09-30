<?php

namespace App\Command;

use App\Services\Mails;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use IntlDateFormatter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'venta',
    description: 'Comando para anular reservaciones incompletas',
)]
class VentaEmailCommand extends Command {

    public function __construct(private EntityManagerInterface $entityManagerInterface, private Mails $mails) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        try {

            $conn = $this->entityManagerInterface->getConnection();

            $sql = "
            select sum(r.precio_real), DATE_TRUNC('month', r.created_at) from reservacion r 
where r.transaccion_id notnull and r.created_at >'20240101' and r.moneda = 'GTQ' GROUP BY DATE_TRUNC('month', r.created_at) order by date_trunc ASC;
            ";

            $resultSet = $conn->executeQuery($sql);

            $resp = $resultSet->fetchAllAssociative();

            $sql = "
            select sum(r.precio_dolar), DATE_TRUNC('month', r.created_at) from reservacion r 
where r.transaccion_id notnull and r.created_at >'20240101' and r.moneda = 'USD' GROUP BY DATE_TRUNC('month', r.created_at) order by date_trunc ASC;
            ";

            $resultSet = $conn->executeQuery($sql);

            $resp2 = $resultSet->fetchAllAssociative();


            $fmt = new IntlDateFormatter(
                "es_ES",
                IntlDateFormatter::FULL,
                IntlDateFormatter::FULL,
                'America/Guatemala',
                IntlDateFormatter::GREGORIAN
            );
            $fmt->setPattern('MMMM Y');
            $qtz = array_map(fn ($i) => [
                'date_trunc' => \datefmt_format($fmt, new \DateTime($i['date_trunc'])),
                'sum' => number_format($i['sum'], 2, '.', ',')
            ], $resp);
            $usd = array_map(fn ($i) => [
                'date_trunc' => $fmt->format(new \DateTime($i['date_trunc'])),
                'sum' => number_format($i['sum'], 2, '.', ',')
            ], $resp2);

            $this->mails->venta($qtz, $usd);

            return Command::SUCCESS;
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
