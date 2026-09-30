<?php

namespace App\Command;

use App\Entity\ClienteReservacion;
use App\Entity\Factura;
use App\Entity\Reservacion;
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
    name: 'anular',
    description: 'Comando para anular reservaciones incompletas',
)]
class AnularReservacionCommand extends Command {

    public function __construct(private EntityManagerInterface $entityManagerInterface, private ReservacionService $reservacionService, private  RemoteDatabaseQueries $remoteDatabaseQueries) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {


        // foreach ([
        //     8236958,
        //     8237011,
        //     8237315,
        //     8237346,
        //     8237365,
        //     8237531,
        //     8237563,
        //     8237564,
        //     8237565,
        // ] as $key => $value) {
        //     $result = $this->remoteDatabaseQueries->anularReservacion($value);
        // }

        // return true;

        // $response = $this->remoteDatabaseQueries->anularReservacion(6257870);

        // foreach ([317189] as $key => $value) {
        // $reservacion = $this->entityManagerInterface->find(Reservacion::class, $value);

        // $total = $reservacion->getPrecio() * 2;
        // $reservacion->setPrecioReal($total)->setPrecio($total)->setPrecioDolar($total);
        // $this->reservacionService->emitirFactura($reservacion);
        // }
        // $this->entityManagerInterface->flush();
        // return Command::SUCCESS;


        // $r = $this->entityManagerInterface->getRepository(Reservacion::class)->findOneBySomeField((new \DateTime())->sub(new \DateInterval('P8D')));
        // $d = [];
        // $r[] = '190.148.126.106';
        // foreach ($r as $key => $value) {
        //     if ('190.148.126.106' == $value?->getTransaccionId()) {
        //         $r = 0;
        //     }
        //     if (strpos(file_get_contents(__DIR__ . "/ip.terminales.fuentedelnorte-access.log"), $value->getTransaccionId()) !== false) {
        //         $d[] = $value->getTransaccioneId();
        //     }
        // }

        // $result = $this->reservacionService->borrar($this->entityManagerInterface->find(Reservacion::class, 30095));
        // if (isset($result['error'])) {
        //     $output->writeln($result['error']);
        // }

        $response = $this->remoteDatabaseQueries->deleteAsientoTemp();

        $response = $this->reservacionService->anularVencidas();

        // $r = $this->entityManagerInterface->getRepository(Reservacion::class)->find(159817);
        // $response = $this->reservacionService->emitirFactura($r);

        // $response = $this->reservacionService->reenviarEmail();
        // test();


        // $output->writeln(($response));
        return Command::SUCCESS;
    }
}
