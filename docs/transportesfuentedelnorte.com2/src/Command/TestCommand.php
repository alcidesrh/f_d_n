<?php

namespace App\Command;

use App\Entity\Empresa;
use App\Entity\Reservacion;
use App\Entity\RutaReservacion;
use App\Entity\SalidaReservacion;
use App\Services\RemoteDatabaseQueries;
use App\Services\SatRestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use Doctrine\ORM\Query\ResultSetMapping;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;

#[AsCommand(
    name: 'Test',
    description: 'Add a short description for your command',
)]
class TestCommand extends Command {
    public function __construct(private RemoteDatabaseQueries $remoteDatabaseQuerie, private EntityManagerInterface $entityManagerInterface, private SatRestService $satRestService) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {

        $rutas_unicas[] = [];
        $first_result = 0;
        $max_result = 100;
        $total = 45165;

        while ($rutas = $this->entityManagerInterface->getRepository(RutaReservacion::class)->findByLimit($first_result, $max_result)) {
            $cont = $eliminadas = 0;
            foreach ($rutas as $key => $value) {
                $cont++;
                $id1 = $value->getEstacionSalida()->getId();
                $id2 = $value->getEstacionLlegada()->getId();
                if (!isset($rutas_unicas[$id1][$id2])) {
                    $rutas_unicas[$id1][$id2] = $value;
                } else {
                    $output->writeln($cont . "Iteraciones");
                    foreach ($value->getReservaciones() as $key => $value2) {
                        $value2->setRuta($rutas_unicas[$id1][$id2]);
                    }
                    $this->entityManagerInterface->remove($value);
                    $this->entityManagerInterface->detach($value);
                    $eliminadas++;
                }
                if ($cont % 100 == 0) {
                    $temp = $cont / 100;
                    $output->writeln("$temp . - Eliminadas: $eliminadas");
                    $this->entityManagerInterface->flush();
                }
                $first_result = 100 - $eliminadas;
                $eliminadas = 0;
            }
        }
        $this->entityManagerInterface->flush();
        $this->entityManagerInterface->clear();

        return Command::SUCCESS;

        foreach ($reservaciones as $key => $reservacion) {
        }

        $reservaciones = $this->entityManagerInterface->getRepository(Reservacion::class)->findAll();
        foreach ($reservaciones as $key => $reservacion) {

            foreach ($reservacion->getSalidasArray() as $key => $salida) {

                foreach ($salida as $key => $value) {
                    $salida->setEmpresaId($this->remoteDatabaseQuerie->getEmpresaIdPorBoletoId($value->getBoletoSistemaId()));
                    break;
                }
            }

            // $empresa_id = $this->remoteDatabaseQuerie->getEmpresaIdPorBoletoId($value)[0]['id'];
            // die();
        }
        $this->entityManagerInterface->flush();
        return Command::SUCCESS;
        // card_accountNumber
        // encryptedPayment_data

        $result = $this->cybersourceApi->request($this->credencialesentication_setup, [
            'paymentInformation' => [
                'card' => [
                    'type' => 001,
                    'expirationMonth' => 12, //$numero,
                    'expirationYear' => 2025, //$codigo_seguridad,
                    'number' => 4000000000000101, //$expira_mes,
                ],
            ],
            'clientReferenceInformation' => [
                'code' => 'cybs_test',
                'partner' => [
                    'developerId' => 7891234,
                    'solutionId' => 89012345
                ]
            ]
        ]);
        $empresas = $this->entityManagerInterface->getRepository(Empresa::class)->findBy(['id' => 2]);
        foreach ($empresas as $key => $empresa) {
            foreach ($data as $key => $value2) {
                // if ($value2['pioneraNum']) {
                //     $result = $this->satRestService->getDirecciones(\strtoupper(\preg_replace("/[^A-Za-z0-9 ]/", '', $empresa->getNit())), $value2['pioneraNum']);
                //     if (\is_array($result) && isset($result['Direccion'])) {
                //         $this->remoteDatabaseQuerie->updateDireccionEstacion($value2['id'], $result['Direccion']);
                //     }
                // }
                if ($value2['mayaoroNum']) {
                    $result = $this->satRestService->getDirecciones(\strtoupper(\preg_replace("/[^A-Za-z0-9 ]/", '', $empresa->getNit())), $value2['mayaoroNum']);
                    if (\is_array($result) && isset($result['Direccion'])) {
                        $this->remoteDatabaseQuerie->updateDireccionEstacion($value2['id'], $result['Direccion']);
                    }
                }
                // if ($value2['rositaNum']) {
                //     $result = $this->satRestService->getDirecciones(\strtoupper(\preg_replace("/[^A-Za-z0-9 ]/", '', $empresa->getNit())), $value2['mayaoroNum']);
                //     if (\is_array($result) && isset($result['Direccion'])) {
                //         $this->remoteDatabaseQuerie->updateDireccionEstacion($value2['id'], $result['Direccion']);
                //     }
                // }
            }
        }
        return Command::SUCCESS;
    }
}
