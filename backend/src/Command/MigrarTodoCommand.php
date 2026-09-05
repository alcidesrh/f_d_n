<?php

namespace App\Command;

use App\Migration\Limpiador;
use App\Migration\Migrador;
use App\Migration\MigradorEstaticos;
use App\Migration\MigradorIAM;
use App\Services\EntityConfigSynchronizer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\DBAL\Connection;

#[
    AsCommand(
        name: "app:migrar:todo",
        description: "Ejecuta la migración completa: 1) reset, 2) estáticos, 3) IAM, 4) config, 5) itinerarios + boletos/asientos vendidos",
    ),
]
class MigrarTodoCommand extends Command
{
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

    protected function configure(): void
    {
        $this->addOption(
            "clean",
            null,
            InputOption::VALUE_NONE,
            "Limpia la BD antes de migrar",
        )
            ->addOption(
                "estaticos",
                null,
                InputOption::VALUE_NONE,
                "Salta la migración Estaticos",
            )
            ->addOption(
                "iam",
                null,
                InputOption::VALUE_NONE,
                "Salta la migración IAM",
            )
            ->addOption(
                "config",
                null,
                InputOption::VALUE_NONE,
                "Salta la sincronización de EntityConfiguration",
            )
            ->addOption(
                "data",
                null,
                InputOption::VALUE_NONE,
                "Salta la sincronización de EntityConfiguration",
            )
            ->addOption(
                "itinerarios",
                null,
                InputOption::VALUE_OPTIONAL,
                "Cantidad de boletos a migrar",
                "100",
            )
            ->addOption(
                "entities",
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                "Who do you want to greet (separate multiple names with a space)?",
                [],
            );
        // ->addArgument('boletos', InputArgument::OPTIONAL, 'Cantidad de boletos a migrar', '100')
        // ->addArgument(
        //     'entities',
        //     InputArgument::IS_ARRAY,
        //     'Who do you want to greet (separate multiple names with a space)?'
        // );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        //php bin/console app:migrar:todo --flag-clean --flag-estaticos --flag-iam --flag-data
        $clean = (bool) $input->getOption("clean");
        $flagEstatico = (bool) $input->getOption("estaticos");
        $flagIam = (bool) $input->getOption("iam");
        $flagConfig = (bool) $input->getOption("config");
        $flagData = (bool) $input->getOption("data");
        $itinerarios = (int) $input->getOption("itinerarios");
        ini_set("memory_limit", "2G");
        // Reset the debug data holder to avoid memory exhaustion from
        // BacktraceDebugDataHolder accumulating all migration queries.
        $this->resetDebugDataHolder();
        $start = microtime(true);
        $allCounters = [];
        $steps = [];

        // ─── Paso 1: Reset (opcional) ─────────────────────────────
        if ($clean) {
            $output->writeln("<info>[1/5] Limpiando base de datos...</info>");
            // $this->limpiador->limpiar();
            $this->resetDB($input, $output, true);
            $output->writeln("<info>✓ Base de datos limpiada</info>");
            $steps[] = "reset";
        }

        // ─── Paso 2: Datos estáticos ──────────────────────────────
        $this->resetDebugDataHolder();
        if ($flagEstatico) {
            $output->writeln("<info>[2/5] Migrando datos estáticos...</info>");
            try {
                $contadores = $this->migradorEstaticos->migrar($output);
                $this->resetDebugDataHolder();
                $allCounters = array_merge($allCounters, $contadores);
                $steps[] = "estaticos";
            } catch (\Throwable $e) {
                $this->resetDebugDataHolder();
                $output->writeln(
                    "<error>Error en datos estáticos: {$e->getMessage()}</error>",
                );
                return Command::FAILURE;
            }
        }
        // ─── Paso 3: IAM ──────────────────────────────────────────
        if ($flagIam) {
            $this->resetDebugDataHolder();
            $output->writeln(
                "<info>[3/5] Migrando IAM (roles, permisos, acciones)...</info>",
            );
            try {
                $contadores = $this->migradorIAM->migrar($output);
                $allCounters = array_merge($allCounters, $contadores);
                $steps[] = "iam";
            } catch (\Throwable $e) {
                $output->writeln(
                    "<error>Error en IAM: {$e->getMessage()}</error>",
                );
                return Command::FAILURE;
            }
        }
        // ─── Paso 4: EntityConfiguration ─────────────────────────
        if ($flagConfig) {
            $output->writeln(
                "<info>[4/5] Sincronizando EntityConfiguration...</info>",
            );
            try {
                $metadataFactory = $this->entityManager->getMetadataFactory();
                $synced = 0;
                foreach ($metadataFactory->getAllMetadata() as $metadata) {
                    if (
                        !$metadata->isMappedSuperclass &&
                        !$metadata->isEmbeddedClass
                    ) {
                        $shortName = $metadata
                            ->getReflectionClass()
                            ->getShortName();
                        if (
                            !in_array(
                                $shortName,
                                [
                                    "EntityConfiguration",
                                    "CollectionFieldConfig",
                                    "FormFieldConfig",
                                ],
                                true,
                            )
                        ) {
                            $this->configSynchronizer->syncEntity($shortName);
                            $synced++;
                        }
                    }
                }
                $output->writeln(
                    "<info>✓ {$synced} entidades sincronizadas</info>",
                );
                $steps[] = "config";
            } catch (\Throwable $e) {
                $output->writeln(
                    "<error>Error en EntityConfiguration: {$e->getMessage()}</error>",
                );
                return Command::FAILURE;
            }
        }
        if ($flagData) {
            // ─── Paso 5: Salidas + Boletos (desde itinerario) ───────────
            $this->resetDebugDataHolder();
            $output->writeln(
                "<info>[5/5] Migrando itinerarios y boletos desde itinerarios...</info>",
            );
            $resetFn = function () {
                $this->resetDebugDataHolder();
            };
            try {
                $contadores = $this->migrador->migrarSalida(
                    $itinerarios,
                    $output,
                    $resetFn,
                );
                $this->resetDebugDataHolder();

                $allCounters = array_merge($allCounters, $contadores);
                $steps[] = "itinerarios+boletos";
            } catch (\Throwable $e) {
                $output->writeln(
                    "<error>Error en boletos: {$e->getMessage()}</error>",
                );
                return Command::FAILURE;
            }

            // ─── Resumen final ────────────────────────────────────────

            $table = new Table($output);
            $table->setHeaders(["Entidad", "Registros"]);
            foreach ($allCounters as $entity => $count) {
                if ($count > 0) {
                    $table->addRow([$entity, $count]);
                }
            }
            $table->render();
        }
        $elapsed = microtime(true) - $start;

        $total = array_sum($allCounters);
        $output->writeln("");
        $output->writeln(
            sprintf(
                "<info>Pasos ejecutados: %s</info>",
                implode(" → ", $steps),
            ),
        );
        $output->writeln(sprintf("<info>Total registros: %d</info>", $total));
        $output->writeln(
            sprintf("<info>Tiempo total: %.2f segundos</info>", $elapsed),
        );

        return Command::SUCCESS;
    }

    private function resetDebugDataHolder(): void
    {
        $app = $this->getApplication();
        if (!$app instanceof Application) {
            return;
        }
        $container = $app->getKernel()->getContainer();
        if ($container && $container->has("doctrine.debug_data_holder")) {
            $container->get("doctrine.debug_data_holder")->reset();
        }
    }
    public function resetDB(
        InputInterface $input,
        ?OutputInterface $output,
        ?bool $hard,
    ): int {
        $io = new SymfonyStyle($input, $output);
        if ($hard) {
            // $io->warning('HARD RESET: se dropeará y recreará la base de datos');
            // if (!$io->confirm('¿Continuar?', false)) {
            //     return Command::SUCCESS;
            // }

            $io->section("Dropeando base de datos...");
            $this->entityManager
                ->getConnection()
                ->executeStatement("DROP SCHEMA public CASCADE");
            $this->entityManager
                ->getConnection()
                ->executeStatement("CREATE SCHEMA public");
            $this->entityManager
                ->getConnection()
                ->executeStatement("GRANT ALL ON SCHEMA public TO PUBLIC");
            $io = new SymfonyStyle($input, $output);

            $metadata = $this->entityManager
                ->getMetadataFactory()
                ->getAllMetadata();

            $tool = new SchemaTool($this->entityManager);
            $tool->createSchema($metadata);

            $io->success("Base de datos recreada y migraciones ejecutadas");
        } else {
            $io->section("Truncando tablas migrables...");
            $this->limpiador->limpiar();
            $io->success("Tablas migrables truncadas");
        }

        return Command::SUCCESS;
    }

    // #[AsCommand('app:migrar:estaticos')]
    public function estaticos(
        #[Argument] array $entities = [],
        ?OutputInterface $output = null,
    ): int {
        $output->writeln("<info>Migrando datos estáticos...</info>");
        try {
            $contadores = $this->migradorEstaticos->migrar($output, $entities);
        } catch (\Throwable $e) {
            $output->writeln(
                "<error>Error en datos estáticos: {$e->getMessage()}</error>",
            );
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
