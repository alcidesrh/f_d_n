<?php

declare(strict_types=1);

namespace App\Migration\Job;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Lanza los jobs como procesos CLI en segundo plano:
 *   php bin/console app:migracion:ejecutar <jobId>
 *
 * El child hereda el entorno del contenedor y escribe su progreso en
 * var/migration/<jobId>/status.json (ver Progreso).
 *
 * IMPORTANTE (FrankenPHP): en modo worker, los procesos hijo creados con
 * symfony/process mueren al terminar el request (el worker mata su grupo de
 * procesos), así que el job se lanza con `setsid` — nueva sesión/grupo de
 * procesos, sin fds heredados y en segundo plano — para que sobreviva al
 * request y a sus propios padres.
 */
final class EjecutorDeJobs
{
    public function __construct(
        private readonly AlmacenDeJobs $almacen,
        #[
            Autowire(param: "kernel.project_dir"),
        ]
        private readonly string $proyectoDir,
    ) {}

    public function lanzar(string $id): void
    {
        $php = PHP_BINDIR . "/php";
        if (!is_executable($php)) {
            // En FrankenPHP (worker) PHP_BINDIR apunta al directorio del runtime.
            $php = PHP_BINARY;
        }

        // Salida del child (stdout/stderr) por si algo falla antes de que el
        // propio comando escriba su log en var/migration/<id>/output.log.
        $logProceso = sprintf(
            "%s/var/migration/%s/proceso.log",
            $this->proyectoDir,
            $id,
        );

        $cmd = sprintf(
            "setsid %s %s %s %s > %s 2>&1 < /dev/null & echo $!",
            escapeshellarg($php),
            escapeshellarg($this->proyectoDir . "/bin/console"),
            escapeshellarg("app:migracion:ejecutar"),
            escapeshellarg($id),
            escapeshellarg($logProceso),
        );

        $salida = [];
        exec($cmd, $salida, $codigo);

        $status = $this->almacen->leer($id) ?? [];
        $status["pid"] = (int) ($salida[0] ?? 0);
        $this->almacen->escribir($id, $status);
    }
}
