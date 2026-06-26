<?php

namespace App\Command;

use App\Entity\Servicio;
use App\Migration\Mapeador;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Target;

#[AsCommand(
    name: 'migracion',
    description: 'Add a short description for your command',
)]
class MigracionCommand extends Command {
    const SQL_TODO = "select top 10 
    bt.*, * from salida s 
    --join boleto b on b.salida_id = s.id 
    join itineario i on i.id = s.itinerario_id 
    join bus_tipo bt on bt.id = i.tipo_bus_id 
    join bus bu on bu.tipo_id = bt.id
    join empresa e on e.id = s.empresa_id 
    join ruta r on r.codigo = i.ruta_codigo 
    join estacion e2 on e2.id = r.estacion_origen_id 
    join estacion e3 on e3.id = r.estacion_destino_id
    join tarifas_boleto tb on (tb.clase_bus_id = bt.id and tb.estacion_origen_id = e2.id and tb.estacion_destino_id = e3.id)
    where s.estado_id != 4
    order by s.fecha desc";
    public function __construct(
        private Connection $newConn,
        #[Target('oldPdo')] private \PDO $oldPdo,
        private Mapeador $mapeador,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {

        if ($output) $output->write('<info>Servicios...</info>');

        $rows = $this->fetchOld(self::SQL_TODO);
        foreach ($rows as $row) {
            $servicio = new Servicio();
            // $data = $this->mapeador->asiento();
            // $servicio->setLegacyId();
        }

        $rows = $this->fetchOld('SELECT tb.*, e.id AS empresa_id FROM tarifas_boleto tb CROSS JOIN (SELECT MIN(id) AS id FROM empresa WHERE activo = 1) e AND tb.clase_asiento = 1 ORDER BY tb.id DESC');

        $count = 0;

        foreach ($rows as $row) {
            $lid = (string) $row['id'];
            if ($this->existe('tarifa', $lid)) {
                continue;
            }

            $empresaId = $this->resolveEmpresaId((int) ($row['empresa_id'] ?? 0));
            if (!$empresaId) {
                $empresaId = $this->getFirstEmpresaId();
                if (!$empresaId) {
                    continue;
                }
            }

            $data = $this->mapeador->tarifa($row, $empresaId);
            $this->newConn->executeStatement(
                'INSERT INTO tarifa (id, nombre, precio_clase_a_monto, precio_clase_b_monto, precio_clase_a_moneda, precio_clase_b_moneda, empresa_id, bus_id) VALUES (:id, :nombre, :precio_clase_a_monto, :precio_clase_b_monto, :precio_clase_a_moneda, :precio_clase_b_moneda, :empresa_id, :bus_id)',
                $data
            );
            $count++;
        }
        $this->newConn->commit();

        $rows2 = $this->fetchOld('SELECT tb.*, e.id AS empresa_id FROM tarifas_boleto tb CROSS JOIN (SELECT MIN(id) AS id FROM empresa WHERE activo = 1) e AND tb.clase_asiento = 2 ORDER BY tb.id DESC');

        foreach ($rows2 as $row) {
            $this->tarifaRepository->findOneBy([]);
            $lid = (string) $row['id'];
            if ($this->existe('tarifa', $lid)) {
                continue;
            }

            $empresaId = $this->resolveEmpresaId((int) ($row['empresa_id'] ?? 0));
            if (!$empresaId) {
                $empresaId = $this->getFirstEmpresaId();
                if (!$empresaId) {
                    continue;
                }
            }

            $data = $this->mapeador->tarifa($row, $empresaId);
            $this->newConn->executeStatement(
                'INSERT INTO tarifa (id, nombre, precio_clase_a_monto, precio_clase_b_monto, precio_clase_a_moneda, precio_clase_b_moneda, empresa_id, bus_id) VALUES (:id, :nombre, :precio_clase_a_monto, :precio_clase_b_monto, :precio_clase_a_moneda, :precio_clase_b_moneda, :empresa_id, :bus_id)',
                $data
            );
            $count++;
        }
        $this->newConn->commit();

        if ($output) $output->writeln(" <info>{$count}</info>");
        return $count;
    }

    private function fetchOld(string $sql, array $params = []): \Generator {
        $stmt = $this->oldPdo->prepare($sql);
        $stmt->execute($params);
        while ($row = $stmt->fetch()) {
            yield $row;
        }
    }
}
