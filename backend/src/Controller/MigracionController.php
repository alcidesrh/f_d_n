<?php

declare(strict_types=1);

namespace App\Controller;

use App\Migration\Entidad\Especificacion;
use App\Migration\Entidad\RegistroMigradores;
use App\Migration\IndicadoresMigracion;
use App\Migration\Job\AlmacenDeJobs;
use App\Migration\Job\EjecutorDeJobs;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Panel de migración legado→nuevo (/migracion).
 *
 * Todos los endpoints son de solo lectura salvo POST /ejecutar, que crea un job
 * y lo lanza como proceso CLI en segundo plano (var/migration/<jobId>). El
 * controlador queda deliberadamente en src/Controller/ porque la carga de rutas
 * por atributos solo barre esa carpeta; la lógica vive en src/Migration/.
 *
 * El catálogo de entidades arrastra la conexión SQL Server legacy y solo se
 * resuelve bajo demanda (RegistroMigradores/IndicadoresMigracion son #[Lazy]):
 * /estado, /cancelar, /log y los tipos de job sin entidad funcionan aunque el
 * legado no responda. SondaLegado hace un preflight TCP no bloqueante antes de
 * construir el PDO legacy, así que el catálogo y los indicadores degradan en
 * ~1.5s (503 informativo con legadoAccesible: false) en vez de colgarse ~130s.
 */
//#[IsGranted("ROLE_SUPER_ADMIN")]
#[AsController]
#[Route("/api/migracion")]
class MigracionController extends AbstractController
{
    private const TIPOS = [
        "reset",
        "truncar",
        "estaticos",
        "entidad",
        "iam",
        "config",
        "todo",
    ];

    public function __construct(
        private readonly RegistroMigradores $registro,
        private readonly AlmacenDeJobs $almacen,
        private readonly EjecutorDeJobs $ejecutorDeJobs,
        private readonly IndicadoresMigracion $indicadores,
    ) {}

    #[Route("/entidades", name: "api_migracion_entidades", methods: ["GET"])]
    public function entidades(): JsonResponse
    {
        try {
            $items = [];
            foreach ($this->registro->todos() as $nombre => $migrador) {
                $items[] = [
                    "nombre" => $nombre,
                    "etiqueta" => $migrador->etiqueta(),
                    "dependencias" => $migrador->dependencias(),
                    "tablaFuente" => $migrador->tablaFuente(),
                    "tablasDestino" => $migrador->tablasDestino(),
                    "soportaRangoFechas" => $migrador->soportaRangoFechas(),
                    "soportaCantidad" => $migrador->soportaCantidad(),
                    "totalFuente" => $migrador->totalFuente(
                        Especificacion::vacia(),
                    ),
                ];
            }

            return $this->json($items);
        } catch (\Throwable $e) {
            // El catálogo arrastra la conexión SQL Server legacy: si no responde,
            // el panel degrada (503 informativo) en vez de un 500 genérico.
            return $this->json(
                [
                    "error" =>
                        "No se pudo construir el catálogo de entidades: " .
                        $e->getMessage(),
                    "legadoAccesible" => false,
                ],
                503,
            );
        }
    }

    #[
        Route(
            "/indicadores",
            name: "api_migracion_indicadores",
            methods: ["GET"],
        ),
    ]
    public function indicadores(): JsonResponse
    {
        try {
            $datos = $this->indicadores->todos();
        } catch (\Throwable $e) {
            // El propio IndicadoresMigracion ya degrada el legado a -1; este
            // catch es la red de seguridad si falla algo inesperado (p. ej. la
            // conexión PostgreSQL del contador "nuevo").
            return $this->json(
                [
                    "error" =>
                        "No se pudieron calcular los indicadores: " .
                        $e->getMessage(),
                    "legado" => -1,
                ],
                503,
            );
        }

        return $this->json($datos);
    }

    #[Route("/estado", name: "api_migracion_estado", methods: ["GET"])]
    public function estado(): JsonResponse
    {
        $actual = $this->almacen->hayEjecutando();
        return $this->json([
            "ejecutando" => null !== $actual,
            "actual" => $actual,
            "recientes" => $this->almacen->lista(12),
        ]);
    }

    #[Route("/ejecutar", name: "api_migracion_ejecutar", methods: ["POST"])]
    public function ejecutar(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $tipo = trim((string) ($data["tipo"] ?? ""));

        if (!in_array($tipo, self::TIPOS, true)) {
            return $this->json(
                ["error" => "Tipo de job inválido: {$tipo}"],
                400,
            );
        }

        if (null !== $this->almacen->hayEjecutando()) {
            return $this->json(
                ["error" => "Ya hay una migración en ejecución."],
                409,
            );
        }

        $entidad = trim((string) ($data["entidad"] ?? ""));
        if ("entidad" === $tipo) {
            try {
                if ("" === $entidad || !$this->registro->tiene($entidad)) {
                    return $this->json(
                        ["error" => "Entidad inválida: {$entidad}"],
                        400,
                    );
                }
                $migrador = $this->registro->obtener($entidad);
                if (
                    !$migrador->soportaRangoFechas() &&
                    (!empty($data["desde"]) || !empty($data["hasta"]))
                ) {
                    return $this->json(
                        [
                            "error" => "{$migrador->etiqueta()} no soporta rango de fechas.",
                        ],
                        400,
                    );
                }
            } catch (\Throwable $e) {
                // Validar la entidad construye el catálogo (SQL Server legacy).
                return $this->json(
                    [
                        "error" =>
                            "No se pudo validar la entidad: " .
                            $e->getMessage(),
                        "legadoAccesible" => false,
                    ],
                    503,
                );
            }
        }

        $parametros = [
            "entidad" => "entidad" === $tipo ? $entidad : null,
            "desde" => empty($data["desde"]) ? null : (string) $data["desde"],
            "hasta" => empty($data["hasta"]) ? null : (string) $data["hasta"],
            "cantidad" => !empty($data["cantidad"])
                ? max(1, (int) $data["cantidad"])
                : null,
            "clean" => (bool) ($data["clean"] ?? false),
        ];

        $job = $this->almacen->crear($tipo, $parametros);

        try {
            $this->ejecutorDeJobs->lanzar($job["id"]);
        } catch (\Throwable $e) {
            $job["estado"] = "error";
            $job["error"] =
                "No se pudo lanzar el proceso de migración: " .
                $e->getMessage();
            $this->almacen->escribir($job["id"], $job);

            return $this->json($job, 500);
        }

        return $this->json($job, 202);
    }

    #[
        Route(
            "/ejecutar/{id}/cancelar",
            name: "api_migracion_cancelar",
            methods: ["POST"],
        ),
    ]
    public function cancelar(string $id): JsonResponse
    {
        $job = $this->almacen->leer($id);
        if (!$job) {
            return $this->json(["error" => "Job no encontrado"], 404);
        }

        $job["solicitud_cancelacion"] = true;
        $this->almacen->marcarCancelacion($id);

        return $this->json($job);
    }

    #[Route("/ejecutar/{id}/log", name: "api_migracion_log", methods: ["GET"])]
    public function log(string $id, Request $request): JsonResponse
    {
        $job = $this->almacen->leer($id);
        if (!$job) {
            return $this->json(["error" => "Job no encontrado"], 404);
        }

        $desde = max(0, (int) $request->query->get("desde", "0"));

        return $this->json($this->almacen->leerLog($id, $desde));
    }
}
