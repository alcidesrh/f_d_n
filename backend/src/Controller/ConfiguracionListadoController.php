<?php

declare(strict_types=1);

namespace App\Controller;

use App\Acceso\Autorizacion;
use App\ConfiguracionListado\ConfiguracionesListado;
use App\ConfiguracionListado\ConfiguracionRechazada;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Configuraciones del listado por rol (ADR-043). `GET /api/me/configuracion/{entidad}`
 * entrega a cualquier usuario con sesión la que le toca (más los campos del
 * formulario); `/api/configuracion-listados/{entidad}` es el editor y pide
 * `config.editar`. Lo que escribe responde todas las de la entidad.
 */
final class ConfiguracionListadoController extends AbstractController
{
    private const CONFIGURAR = "config.editar";

    /** Las listOptions vacías salen como `{}`, no `[]`. */
    private const JSON = ["preserve_empty_objects" => true];

    public function __construct(
        private readonly ConfiguracionesListado $configuraciones,
        private readonly Autorizacion $autorizacion,
    ) {}

    #[Route("/api/me/configuracion/{entidad<\w+>}", name: "api_me_configuracion_entidad", methods: ["GET"])]
    public function vigente(string $entidad): JsonResponse
    {
        $vigente = $this->configuraciones->vigente($entidad, $this->autorizacion->permisos());

        return $vigente === null
            ? $this->json(["error" => "No existe la entidad {$entidad}.", "codigo" => "entidad_desconocida"], 404)
            : $this->json($vigente, 200, [], self::JSON);
    }

    /** `{ configuraciones: [{ id, nombre, position, porDefecto, roles: [id], listOptions, collectionFieldConfig }], roles: [{ id, nombre }] }` */
    #[Route("/api/configuracion-listados/{entidad<\w+>}", name: "api_configuracion_listados", methods: ["GET"])]
    public function editor(string $entidad): JsonResponse
    {
        return $this->configurar(fn() => $this->configuraciones->editor($entidad));
    }

    /** `{ nombre, roles: [id], desde?: id }`; responde también `creada` (su id). */
    #[Route("/api/configuracion-listados/{entidad<\w+>}", name: "api_configuracion_listados_crear", methods: ["POST"])]
    public function crear(string $entidad, Request $request): JsonResponse
    {
        return $this->configurar(fn() => $this->configuraciones->crear($entidad, $request->toArray()), 201);
    }

    /** `{ nombre?, roles?: [id], listOptions?, columnas?: [{ id, label, position, visible, … }] }` */
    #[Route("/api/configuracion-listados/{entidad<\w+>}/{id<\d+>}", name: "api_configuracion_listados_guardar", methods: ["PUT"])]
    public function guardar(string $entidad, int $id, Request $request): JsonResponse
    {
        return $this->configurar(fn() => $this->configuraciones->guardar($entidad, $id, $request->toArray()));
    }

    #[Route("/api/configuracion-listados/{entidad<\w+>}/{id<\d+>}", name: "api_configuracion_listados_eliminar", methods: ["DELETE"])]
    public function eliminar(string $entidad, int $id): JsonResponse
    {
        return $this->configurar(fn() => $this->configuraciones->eliminar($entidad, $id));
    }

    private function configurar(callable $operacion, int $estado = 200): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::CONFIGURAR);
        try {
            return $this->json($operacion(), $estado, [], self::JSON);
        } catch (ConfiguracionRechazada $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        }
    }
}
