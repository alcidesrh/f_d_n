<?php

declare(strict_types=1);

namespace App\Controller;

use App\Ruta\EdicionRutas;
use App\Ruta\RutaRechazada;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Rutas con sus paradas (ADR-030). Verlas pide `salida.ver`; crearlas,
 * editarlas, duplicarlas y eliminarlas, `ruta.editar`.
 */
#[Route("/api/gestion-rutas", name: "api_gestion_rutas_")]
final class RutaController extends AbstractController
{
    public const EDITAR = "ruta.editar";

    public function __construct(private readonly EdicionRutas $rutas) {}

    #[Route("", name: "listar", methods: ["GET"])]
    public function listar(): JsonResponse
    {
        $this->denyAccessUnlessGranted(SalidaController::VER);

        return $this->json(["items" => $this->rutas->listar(), "puedeEditar" => $this->isGranted(self::EDITAR)]);
    }

    /** Lugares y clases de bus para el editor. */
    #[Route("/opciones", name: "opciones", methods: ["GET"])]
    public function opciones(): JsonResponse
    {
        $this->denyAccessUnlessGranted(SalidaController::VER);

        return $this->json($this->rutas->opciones() + ["puedeEditar" => $this->isGranted(self::EDITAR)]);
    }

    #[Route("/{id<\d+>}", name: "leer", methods: ["GET"])]
    public function leer(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(SalidaController::VER);

        return $this->responder(fn() => $this->rutas->leer($id));
    }

    /** `{ nombre, kilometros?, activo?, paradas: [{ enclaveId, minutos?, km?, permiteSubir?, permiteBajar?, tiempos?: [{ claseId, minutos }] }] }` */
    #[Route("", name: "crear", methods: ["POST"])]
    public function crear(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::EDITAR);

        return $this->responder(fn() => $this->rutas->guardar(null, $request->toArray()), 201);
    }

    #[Route("/{id<\d+>}", name: "guardar", methods: ["PUT"])]
    public function guardar(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::EDITAR);

        return $this->responder(fn() => $this->rutas->guardar($id, $request->toArray()));
    }

    /** `{ invertir?: bool }`: la copia (o la de regreso) queda inactiva. */
    #[Route("/{id<\d+>}/duplicar", name: "duplicar", methods: ["POST"])]
    public function duplicar(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::EDITAR);
        $datos = $request->getContent() === "" ? [] : $request->toArray();

        return $this->responder(fn() => $this->rutas->duplicar($id, (bool) ($datos["invertir"] ?? false)), 201);
    }

    #[Route("/{id<\d+>}", name: "eliminar", methods: ["DELETE"])]
    public function eliminar(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::EDITAR);

        return $this->responder(function () use ($id) {
            $this->rutas->eliminar($id);

            return null;
        }, 204);
    }

    private function responder(callable $operacion, int $estado = 200): JsonResponse
    {
        try {
            return $this->json($operacion(), $estado);
        } catch (RutaRechazada $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        }
    }
}
