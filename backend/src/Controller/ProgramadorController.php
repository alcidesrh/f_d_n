<?php

declare(strict_types=1);

namespace App\Controller;

use App\Programador\EjecucionManual;
use App\Programador\RegistroTareas;
use App\Programador\Tarea;
use Psr\Clock\ClockInterface;
use App\Programador\EstadoDeTarea;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * El programador de tareas visto desde la administración (ADR-044): el
 * latido de cada tarea y "correr ahora". Pide `config.editar`.
 */
#[AsController]
#[Route("/api/programador", name: "api_programador_")]
final class ProgramadorController extends AbstractController
{
    private const ADMINISTRAR = "config.editar";

    public function __construct(
        private readonly RegistroTareas $registro,
        private readonly EjecucionManual $ejecucion,
        private readonly ClockInterface $reloj,
        #[Autowire(env: 'PROGRAMADOR_HORA_REPORTES')] private readonly string $horaReportes,
    ) {}

    /** `{ tareas: [{ codigo, nombre, cuando, frecuencia, ultimoInicio, …, detenida }], fallidos }` */
    #[Route("/tareas", name: "tareas", methods: ["GET"])]
    public function tareas(): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ADMINISTRAR);
        $ahora = \DateTimeImmutable::createFromInterface($this->reloj->now());

        return $this->json([
            "tareas" => array_map(fn(EstadoDeTarea $e) => $e->toArray($ahora) + ["cuando" => $e->tarea->cuando($this->horaReportes)], $this->registro->todas()),
            "fallidos" => $this->registro->fallidos(),
        ]);
    }

    /** La pone en la cola ya; el worker la corre con su cerrojo (si ya corre, se omite). */
    #[Route("/tareas/{codigo}/ejecutar", name: "ejecutar", methods: ["POST"], requirements: ["codigo" => "[a-z_]+"])]
    public function ejecutar(string $codigo): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ADMINISTRAR);
        $tarea = Tarea::tryFrom($codigo);
        if ($tarea === null) {
            return $this->json(["error" => "No existe esa tarea.", "codigo" => "tarea_inexistente"], 404);
        }
        $this->ejecucion->encolar($tarea);

        return $this->json(["encolada" => $tarea->value], 202);
    }
}
