<?php

namespace App\Command;

use App\Migration\Limpiador;
use App\Migration\Migrador;
use App\Migration\MigradorEstaticos;
use App\Migration\MigradorIAM;
use App\Services\EntityConfigSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:migrar:todo',
    description: 'Ejecuta la migración completa: 1) reset, 2) estáticos, 3) IAM, 4) config, 5) boletos'
)]
class MigrarTodoCommand extends Command {
    public function __construct(
        private Limpiador $limpiador,
        private MigradorEstaticos $migradorEstaticos,
        private MigradorIAM $migradorIAM,
        private Migrador $migrador,
        private EntityConfigSynchronizer $configSynchronizer,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->addOption('clean', null, InputOption::VALUE_NONE, 'Limpia la BD antes de migrar')
            ->addOption('skip-iam', null, InputOption::VALUE_NONE, 'Salta la migración IAM')
            ->addOption('skip-config', null, InputOption::VALUE_NONE, 'Salta la sincronización de EntityConfiguration')
            ->addArgument('boletos', InputArgument::OPTIONAL, 'Cantidad de boletos a migrar', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $clean = (bool) $input->getOption('clean');
        $skipIam = (bool) $input->getOption('skip-iam');
        $skipConfig = (bool) $input->getOption('skip-config');
        $boletos = (int) $input->getArgument('boletos');
        // Reset the debug data holder to avoid memory exhaustion from
        // BacktraceDebugDataHolder accumulating all migration queries.
        $this->resetDebugDataHolder();

        $start = microtime(true);
        $allCounters = [];
        $steps = [];

        // ─── Paso 1: Reset (opcional) ─────────────────────────────
        if ($clean) {
            $output->writeln('<info>[1/5] Limpiando base de datos...</info>');
            $this->limpiador->limpiar();
            $output->writeln('<info>✓ Base de datos limpiada</info>');
            $steps[] = 'reset';
        }

        // ─── Paso 2: Datos estáticos ──────────────────────────────
        $this->resetDebugDataHolder();
        $output->writeln('<info>[2/5] Migrando datos estáticos...</info>');
        try {
            $contadores = $this->migradorEstaticos->migrar($output);
            $allCounters = array_merge($allCounters, $contadores);
            $steps[] = 'estaticos';
        } catch (\Throwable $e) {
            $output->writeln("<error>Error en datos estáticos: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        // ─── Paso 3: IAM ──────────────────────────────────────────
        if (!$skipIam) {
            $this->resetDebugDataHolder();
            $output->writeln('<info>[3/5] Migrando IAM (roles, permisos, acciones)...</info>');
            try {
                $contadores = $this->migradorIAM->migrar($output);
                $allCounters = array_merge($allCounters, $contadores);
                $steps[] = 'iam';
            } catch (\Throwable $e) {
                $output->writeln("<error>Error en IAM: {$e->getMessage()}</error>");
                return Command::FAILURE;
            }
        }

        // ─── Paso 4: EntityConfiguration ─────────────────────────
        if (!$skipConfig) {
            $output->writeln('<info>[4/5] Sincronizando EntityConfiguration...</info>');
            try {
                $metadataFactory = $this->entityManager->getMetadataFactory();
                $synced = 0;
                foreach ($metadataFactory->getAllMetadata() as $metadata) {
                    if (!$metadata->isMappedSuperclass && !$metadata->isEmbeddedClass) {
                        $shortName = $metadata->getReflectionClass()->getShortName();
                        if (!in_array($shortName, ['EntityConfiguration', 'CollectionFieldConfig', 'FormFieldConfig'], true)) {
                            $this->configSynchronizer->syncEntity($shortName);
                            $synced++;
                        }
                    }
                }
                $output->writeln("<info>✓ {$synced} entidades sincronizadas</info>");
                $steps[] = 'config';
            } catch (\Throwable $e) {
                $output->writeln("<error>Error en EntityConfiguration: {$e->getMessage()}</error>");
                return Command::FAILURE;
            }
        }

        // ─── Paso 5: Boletos (datos variables) ────────────────────
        $output->writeln(sprintf('<info>[5/5] Migrando hasta %d boletos...</info>', $boletos));
        try {
            $contadores = $this->migrador->migrarBoletos($boletos, false, $output);
            $allCounters = array_merge($allCounters, $contadores);
            $steps[] = 'boletos';
        } catch (\Throwable $e) {
            $output->writeln("<error>Error en boletos: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        // ─── Resumen final ────────────────────────────────────────
        $elapsed = microtime(true) - $start;

        $table = new Table($output);
        $table->setHeaders(['Entidad', 'Registros']);
        foreach ($allCounters as $entity => $count) {
            if ($count > 0) {
                $table->addRow([$entity, $count]);
            }
        }
        $table->render();

        $total = array_sum($allCounters);
        $output->writeln('');
        $output->writeln(sprintf('<info>Pasos ejecutados: %s</info>', implode(' → ', $steps)));
        $output->writeln(sprintf('<info>Total registros: %d</info>', $total));
        $output->writeln(sprintf('<info>Tiempo total: %.2f segundos</info>', $elapsed));

        return Command::SUCCESS;
    }

    private function resetDebugDataHolder(): void {
        $app = $this->getApplication();
        if (!$app instanceof Application) {
            return;
        }
        $container = $app->getKernel()->getContainer();
        if ($container && $container->has('doctrine.debug_data_holder')) {
            $container->get('doctrine.debug_data_holder')->reset();
        }
    }
}
