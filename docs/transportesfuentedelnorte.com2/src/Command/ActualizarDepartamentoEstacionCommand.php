<?php

namespace App\Command;

use App\Entity\Departamento;
use App\Entity\Estacion;
use App\Repository\DepartamentoRepository;
use App\Repository\EstacionRepository;
use App\Services\RemoteDatabaseQueries;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ActualizarDepartamentoEstacion',
    description: 'Add a short description for your command',
)]
class ActualizarDepartamentoEstacionCommand extends Command {
    public function __construct(private RemoteDatabaseQueries $remote_database_queries, private EstacionRepository $estacion_repository, private DepartamentoRepository $departamento_repository, private EntityManagerInterface $em) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {

        $io = new SymfonyStyle($input, $output);

        $start = new \DateTime();

        try {
            foreach ($this->estacion_repository->findAll() as $key => $value) {
                $value->setActiva(false);
            }

            $result = $this->remote_database_queries->getEstaciones();

            if (!empty($result)) {
                $nuevas_estaciones = 0;
                $departamentos = [];

                foreach ($result as $value) {
                    if ($value['estacion_id']) {
                        if ($value['departamento_id'] && !isset($departamentos[$value['departamento_id']])) {
                            if (!($departamento = $this->departamento_repository->find($value['departamento_id']))) {
                                $departamento = new Departamento();
                                $departamento->setId($value['departamento_id']);
                                $this->em->persist($departamento);
                            }
                            $departamento->setNombre($value['departamento_nombre']);
                            $departamentos[$value['departamento_id']] = $departamento;
                        }

                        if (!($estacion = $this->estacion_repository->findOneBy(['alias' => $value['estacion_alias']]))) {
                            $estacion = new Estacion();
                            $this->em->persist($estacion);
                            ++$nuevas_estaciones;
                        }

                        $estacion->setNombre($value['estacion_nombre'])->setDireccion($value['estacion_direccion'])->setAlias($value['estacion_alias'])->setActiva($value['estacion_activa'])->setEstacionId($value['estacion_id']);

                        if (isset($departamentos[$value['departamento_id']])) {
                            $estacion->setDepartamento($departamentos[$value['departamento_id']]);
                        }
                    }
                }

                $this->em->flush();
            }
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $segundos = (new \DateTime())->getTimestamp() - $start->getTimestamp();

        $io->success(sprintf('Se crearon %d en %s segundos.', $nuevas_estaciones ?? 0, $segundos));

        return Command::SUCCESS;
    }
}
