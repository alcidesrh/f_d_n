<?php

declare(strict_types=1);

namespace App\Migration\Command;

use App\Migration\Entidad\EjecutorEntidad;
use App\Migration\Entidad\Especificacion;
use App\Migration\Entidad\RegistroMigradores;
use App\Migration\Job\AlmacenDeJobs;
use App\Migration\Job\Progreso;
use App\Migration\Job\SalidaJobOutput;
use App\Migration\Reseteador;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Proceso en segundo plano que ejecuta un job de migración creado desde /migracion.
 * No debe ejecutarse a mano: lo lanza EjecutorDeJobs como `php bin/console
 * app:migracion:ejecutar <jobId>` y escribe su progreso en var/migration/<jobId>.
 */
#[
    AsCommand(
        name: "app:migracion:ejecutar",
        description: "Ejecuta un job de migración del panel /migracion (uso interno)",
    ),
]
class EjecutarJobCommand extends Command
{
    public function __construct(
        private readonly AlmacenDeJobs $almacen,
        private readonly RegistroMigradores $registro,
        private readonly Reseteador $reseteador,
        private readonly EjecutorEntidad $ejecutorEntidad,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument("job", InputArgument::REQUIRED, "ID del job");
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $id = (string) $input->getArgument("job");
        $status = $this->almacen->leer($id);
        if (!$status) {
            $output->writeln("<error>Job no encontrado: {$id}</error>");

            return Command::FAILURE;
        }

        ini_set("memory_limit", "2G");
        $this->resetDebugDataHolder();

        $salida = new SalidaJobOutput($this->almacen, $id);
        $progreso = new Progreso($this->almacen, $id);

        $status["estado"] = "running";
        $status["iniciado_en"] = date("c");
        $status["pid"] = getmypid();
        $this->almacen->escribir($id, $status);

        $salida->writeln(
            sprintf(
                "Job %s iniciado [%s]",
                $id,
                $status["tipo"] .
                    ($status["entidad"] ? " → " . $status["entidad"] : ""),
            ),
        );

        $inicio = microtime(true);
        $contadores = [];
        try {
            $contadores = $this->ejecutar($status, $salida, $progreso);
            if ($progreso->debeCancelar()) {
                $salida->writeln(
                    "<comment>Job cancelado por el usuario.</comment>",
                );
            } else {
                $salida->writeln("<info>Job completado correctamente.</info>");
            }
            $estadoFinal = $progreso->debeCancelar() ? "cancelado" : "done";
        } catch (\Throwable $e) {
            $estadoFinal = "error";
            $salida->writeln(
                sprintf("<error>Error: %s</error>", $e->getMessage()),
            );
            $salida->writeln($e->getTraceAsString());
            $status["error"] = $e->getMessage() . "\n" . $e->getTraceAsString();
        }

        $status["estado"] = $estadoFinal;
        $status["contadores"] = array_merge(
            (array) ($status["contadores"] ?? []),
            $contadores,
        );
        $status["duracion"] = round(microtime(true) - $inicio, 2);
        $status["terminado_en"] = date("c");
        $this->almacen->escribir($id, $status);

        return $estadoFinal === "error" ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $status
     *
     * @return array<string, int>
     */
    private function ejecutar(
        array $status,
        SalidaJobOutput $salida,
        Progreso $progreso,
    ): array {
        $tipo = (string) $status["tipo"];
        $params = (array) ($status["parametros"] ?? []);
        $contadores = [];

        switch ($tipo) {
            case "reset":
                $salida->writeln(
                    "<info>Reset duro: se dropeará y recreará la BD…</info>",
                );
                $this->reseteador->hard();
                $salida->writeln(
                    "<info>✓ BD recreada con el esquema completo.</info>",
                );
                break;

            case "truncar":
                $salida->writeln("<info>Truncando tablas migrables…</info>");
                $this->reseteador->soft();
                $salida->writeln("<info>✓ Tablas migrables truncadas.</info>");
                break;

            case "estaticos":
                $contadores = $this->ejecutarEstaticos($salida, $progreso);
                break;

            case "iam":
                $contadores = $this->registro
                    ->obtener("iam")
                    ->migrar(Especificacion::vacia("iam"), $salida, $progreso);
                break;

            case "config":
                $contadores = $this->registro
                    ->obtener("config")
                    ->migrar(
                        Especificacion::vacia("config"),
                        $salida,
                        $progreso,
                    );
                break;

            case "entidad":
                $es = Especificacion::desdeArray($params);
                $contadores = $this->ejecutorEntidad->ejecutar(
                    $es->entidad,
                    $es,
                    $salida,
                    $progreso,
                );
                break;

            case "todo":
                if (!empty($params["clean"])) {
                    $salida->writeln("<info>[1/5] Reset de BD…</info>");
                    $this->reseteador->hard();
                }
                $salida->writeln("<info>[2/5] Migrando estáticos…</info>");
                $contadores = array_merge(
                    $contadores,
                    $this->ejecutarEstaticos($salida, $progreso),
                );
                if ($progreso->debeCancelar()) {
                    break;
                }
                $salida->writeln("<info>[3/5] Migrando IAM…</info>");
                $contadores = array_merge(
                    $contadores,
                    $this->registro
                        ->obtener("iam")
                        ->migrar(
                            Especificacion::vacia("iam"),
                            $salida,
                            $progreso,
                        ),
                );
                if ($progreso->debeCancelar()) {
                    break;
                }
                $salida->writeln(
                    "<info>[4/5] Sincronizando EntityConfiguration…</info>",
                );
                $contadores = array_merge(
                    $contadores,
                    $this->registro
                        ->obtener("config")
                        ->migrar(
                            Especificacion::vacia("config"),
                            $salida,
                            $progreso,
                        ),
                );
                if ($progreso->debeCancelar()) {
                    break;
                }
                $salida->writeln("<info>[5/5] Migrando salidas (lote)…</info>");
                $es = Especificacion::desdeArray(
                    ["entidad" => "salida"] + $params,
                );
                $contadores = array_merge(
                    $contadores,
                    $this->ejecutorEntidad->ejecutar(
                        "salida",
                        $es,
                        $salida,
                        $progreso,
                    ),
                );
                break;

            default:
                throw new \InvalidArgumentException(
                    "Tipo de job desconocido: {$tipo}",
                );
        }

        return $contadores;
    }

    /**
     * Flujo de estáticos del catálogo (mismo orden que MigradorEstaticos::migrar),
     * entidad por entidad para poder informar progreso y cancelar entre pasos.
     *
     * @return array<string, int>
     */
    private function ejecutarEstaticos(
        SalidaJobOutput $salida,
        Progreso $progreso,
    ): array {
        $contadores = [];
        foreach (RegistroMigradores::ORDEN_ESTATICOS as $nombre) {
            $migrador = $this->registro->obtener($nombre);
            $contadores = array_merge(
                $contadores,
                $migrador->migrar(
                    Especificacion::vacia($nombre),
                    $salida,
                    $progreso,
                ),
            );
            if ($progreso->debeCancelar()) {
                $salida->writeln(
                    "<comment>Flujo de estáticos cancelado.</comment>",
                );
                break;
            }
        }

        return $contadores;
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
}
