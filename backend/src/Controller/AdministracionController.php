<?php

declare(strict_types=1);

namespace App\Controller;

use App\Administracion\AdministracionRechazada;
use App\Administracion\PanelAdministracion;
use App\Entity\Usuario;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Cajas de `/administracion`. Cualquier usuario con sesión ve las suyas
 * (`GET`); configurarlas (`?configurar=1` y todo lo que escribe) pide
 * `config.editar`. Lo que escribe responde la configuración completa.
 */
#[Route("/api/administracion", name: "api_administracion_")]
final class AdministracionController extends AbstractController
{
    public const CONFIGURAR = "config.editar";

    /** Los `parametros` vacíos de un destino salen como `{}`, no `[]`. */
    private const JSON = ["preserve_empty_objects" => true];

    public function __construct(private readonly PanelAdministracion $panel) {}

    /** `{ puedeConfigurar, secciones }`; con `?configurar=1`, todas (ocultas incluidas) con su acceso. */
    #[Route("", name: "leer", methods: ["GET"])]
    public function leer(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $puede = $this->isGranted(self::CONFIGURAR);
        if ($request->query->getBoolean("configurar")) {
            $this->denyAccessUnlessGranted(self::CONFIGURAR);

            return $this->json(["puedeConfigurar" => true, "secciones" => $this->panel->configuracion()], 200, [], self::JSON);
        }

        return $this->json(["puedeConfigurar" => $puede, "secciones" => $this->panel->paraUsuario($usuario)], 200, [], self::JSON);
    }

    /** `{ titulo, icono, color, activa?, acceso?: { roles: [id], usuarios: [id] } }` */
    #[Route("/secciones", name: "crear_seccion", methods: ["POST"])]
    public function crearSeccion(Request $request): JsonResponse
    {
        return $this->configurar(fn() => $this->panel->crearSeccion($request->toArray()), 201);
    }

    #[Route("/secciones/{id<\d+>}", name: "guardar_seccion", methods: ["PUT"])]
    public function guardarSeccion(int $id, Request $request): JsonResponse
    {
        return $this->configurar(fn() => $this->panel->guardarSeccion($id, $request->toArray()));
    }

    /** Elimina la caja con sus enlaces. */
    #[Route("/secciones/{id<\d+>}", name: "eliminar_seccion", methods: ["DELETE"])]
    public function eliminarSeccion(int $id): JsonResponse
    {
        return $this->configurar(fn() => $this->panel->eliminarSeccion($id));
    }

    /** `{ etiqueta, icono, claves?, abrir: { ruta, parametros? } | { path }, agregar?: …, activo?, acceso? }` */
    #[Route("/secciones/{id<\d+>}/enlaces", name: "crear_enlace", methods: ["POST"])]
    public function crearEnlace(int $id, Request $request): JsonResponse
    {
        return $this->configurar(fn() => $this->panel->crearEnlace($id, $request->toArray()), 201);
    }

    #[Route("/enlaces/{id<\d+>}", name: "guardar_enlace", methods: ["PUT"])]
    public function guardarEnlace(int $id, Request $request): JsonResponse
    {
        return $this->configurar(fn() => $this->panel->guardarEnlace($id, $request->toArray()));
    }

    #[Route("/enlaces/{id<\d+>}", name: "eliminar_enlace", methods: ["DELETE"])]
    public function eliminarEnlace(int $id): JsonResponse
    {
        return $this->configurar(fn() => $this->panel->eliminarEnlace($id));
    }

    /** `{ secciones: [{ id, enlaces: [id] }] }` con todas las cajas y todos los enlaces. */
    #[Route("/orden", name: "ordenar", methods: ["PUT"])]
    public function ordenar(Request $request): JsonResponse
    {
        return $this->configurar(fn() => $this->panel->ordenar($request->toArray()));
    }

    private function configurar(callable $operacion, int $estado = 200): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::CONFIGURAR);
        try {
            return $this->json(["puedeConfigurar" => true, "secciones" => $operacion()], $estado, [], self::JSON);
        } catch (AdministracionRechazada $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        }
    }
}
