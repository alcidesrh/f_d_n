<?php

declare(strict_types=1);

namespace App\Migration\Job;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Almacenamiento en filesystem del estado de los jobs de migración.
 *
 * Estructura: var/migration/<jobId>/status.json + output.log + cancelar.flag
 *
 * El estado vive en filesystem (no en BD) porque el reset duro dropea el esquema
 * y porque los jobs se lanzan como procesos CLI en segundo plano: cada escritura
 * de Progreso actualiza status.json de forma atómica (tmp + rename).
 *
 * Robustez: un job en estado "pending"/"running" cuyo status.json no se actualiza
 * durante más de N segundos se considera huérfano (proceso muerto o reinicio del
 * contenedor) y se marca "abortado", liberando el single-flight.
 */
final class AlmacenDeJobs
{
    public const DIR = "var/migration";
    public const ESTADOS_ACTIVOS = ["pending", "running"];

    /** Un job "running" sin escrituras durante más de una hora: proceso muerto. */
    private const RUNNING_STALE_SEGUNDOS = 3600;

    /** Un job "pending" sin arrancar en 2 minutos: el lanzamiento falló. */
    private const PENDING_STALE_SEGUNDOS = 120;

    private readonly string $directorio;

    public function __construct(
        #[
            Autowire(param: "kernel.project_dir"),
        ]
        private readonly string $proyectoDir,
    ) {
        $this->directorio = rtrim($this->proyectoDir, "/") . "/" . self::DIR;
        @mkdir($this->directorio, 0777, true);
    }

    public function directorio(): string
    {
        return $this->directorio;
    }

    /**
     * @param array<string, mixed> $parametros
     *
     * @return array<string, mixed>
     */
    public function crear(string $tipo, array $parametros): array
    {
        $id = date("Ymd-His") . "-" . substr(bin2hex(random_bytes(4)), 0, 6);
        @mkdir($this->ruta($id), 0777, true);

        $status = [
            "id" => $id,
            "tipo" => $tipo,
            "parametros" => $parametros,
            "entidad" => $parametros["entidad"] ?? null,
            "estado" => "pending",
            "creado_en" => date("c"),
            "iniciado_en" => null,
            "terminado_en" => null,
            "actualizado_en" => date("c"),
            "procesados" => 0,
            "total" => null,
            "contadores" => [],
            "errores" => 0,
            "mensaje" => "",
            "duracion" => null,
        ];
        $this->escribir($id, $status);

        return $status;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function leer(string $id): ?array
    {
        $file = $this->rutaStatus($id);
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string, mixed> $status
     */
    public function escribir(string $id, array $status): void
    {
        $file = $this->rutaStatus($id);
        $status["actualizado_en"] = date("c");
        $tmp = $file . ".tmp";
        file_put_contents(
            $tmp,
            json_encode(
                $status,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ),
        );
        @rename($tmp, $file);
    }

    /**
     * Jobs más recientes (por mtime del status.json), con sus estados.
     *
     * @return array<int, array<string, mixed>>
     */
    public function lista(int $limite = 20): array
    {
        $dirs = glob($this->directorio . "/*", GLOB_ONLYDIR) ?: [];
        usort(
            $dirs,
            fn(string $a, string $b): int => (filemtime($b) ?: 0) <=>
                (filemtime($a) ?: 0),
        );

        $jobs = [];
        foreach (array_slice($dirs, 0, $limite) as $dir) {
            $status = $this->leer(basename($dir));
            if ($status) {
                $jobs[] = $status;
            }
        }

        return $jobs;
    }

    /**
     * El job actualmente en ejecución (single-flight) o null.
     *
     * @return array<string, mixed>|null
     */
    public function hayEjecutando(): ?array
    {
        foreach ($this->lista(100) as $job) {
            $estado = (string) ($job["estado"] ?? "");
            if (!in_array($estado, self::ESTADOS_ACTIVOS, true)) {
                continue;
            }

            $stale =
                $estado === "running"
                    ? self::RUNNING_STALE_SEGUNDOS
                    : self::PENDING_STALE_SEGUNDOS;
            $mtime = @filemtime($this->rutaStatus($job["id"])) ?: time();

            if (time() - $mtime > $stale) {
                $job["estado"] = "abortado";
                $job["mensaje"] = sprintf(
                    "Abortado: sin señales de vida en los últimos %ds.",
                    $stale,
                );
                $this->escribir($job["id"], $job);
                continue;
            }

            return $job;
        }

        return null;
    }

    public function marcarCancelacion(string $id): void
    {
        @file_put_contents($this->rutaCancelar($id), date("c") . PHP_EOL);
    }

    public function cancelacionSolicitada(string $id): bool
    {
        return is_file($this->rutaCancelar($id));
    }

    public function appendLog(string $id, string $texto): void
    {
        // Sin TTY: eliminar retornos de carro de los contadores "\r...".
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        @file_put_contents($this->rutaLog($id), $texto, FILE_APPEND);
    }

    /**
     * Tail del log del job desde un offset de bytes.
     *
     * @return array{log: string, offset: int, fin: bool}
     */
    public function leerLog(string $id, int $desde = 0): array
    {
        $file = $this->rutaLog($id);
        $tamano = is_file($file) ? filesize($file) : 0;
        $offset = min(max(0, $desde), $tamano);
        $texto = "";

        if ($offset < $tamano) {
            $handle = fopen($file, "rb");
            if (false !== $handle) {
                fseek($handle, $offset);
                $texto = (string) fread($handle, $tamano - $offset);
                fclose($handle);
            }
        }

        $job = $this->leer($id);
        $vivo =
            null !== $job &&
            in_array(
                (string) ($job["estado"] ?? ""),
                self::ESTADOS_ACTIVOS,
                true,
            );

        return [
            "log" => $texto,
            "offset" => $tamano,
            "fin" => !$vivo && $offset >= $tamano,
        ];
    }

    public function ruta(string $id): string
    {
        return $this->directorio . "/" . $id;
    }

    public function rutaStatus(string $id): string
    {
        return $this->ruta($id) . "/status.json";
    }

    public function rutaLog(string $id): string
    {
        return $this->ruta($id) . "/output.log";
    }

    public function rutaCancelar(string $id): string
    {
        return $this->ruta($id) . "/cancelar.flag";
    }
}
