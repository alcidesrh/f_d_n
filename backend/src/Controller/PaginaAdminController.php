<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ConfiguracionPagina;
use App\Entity\Empresa;
use App\Entity\MensajeContacto;
use App\Entity\Usuario;
use App\Venta\ConsultaVenta;
use App\Venta\EnLinea\AjustesPagina;
use App\Venta\EnLinea\ConsultaComprasWeb;
use App\Venta\EnLinea\FiltroComprasWeb;
use App\Venta\EnLinea\Recargo;
use App\Venta\Excepcion\VentaRechazada;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Dashboard de la página web (ADR-023), en la app del personal: compras en
 * línea con filtro y calculadora, configuración (recargo, venta activa,
 * cierre) y mensajes de contacto. Permiso `pagina.administrar`.
 */
#[AsController]
#[Route("/api/pagina", name: "api_pagina_")]
final class PaginaAdminController extends AbstractController
{
    public const ADMINISTRAR = "pagina.administrar";

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AjustesPagina $ajustes,
    ) {}

    /** Listado filtrado + calculadora. Ver `FiltroComprasWeb::desdeQuery` para los parámetros. */
    #[Route("/compras", name: "compras", methods: ["GET"])]
    public function compras(Request $request, ConsultaComprasWeb $consulta): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ADMINISTRAR);

        return $this->json($consulta->buscar(FiltroComprasWeb::desdeQuery($request->query->all())));
    }

    /** Empresas y estaciones para los filtros. */
    #[Route("/opciones", name: "opciones", methods: ["GET"])]
    public function opciones(ConsultaVenta $consulta): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ADMINISTRAR);

        return $this->json([
            "empresas" => array_map(
                static fn(Empresa $e) => ["id" => $e->getId(), "nombre" => $e->getNombre()],
                $this->em->getRepository(Empresa::class)->findBy([], ["nombre" => "ASC"]),
            ),
            "estaciones" => $consulta->estacionesEnLinea(),
        ]);
    }

    #[Route("/configuracion", name: "configuracion", methods: ["GET"])]
    public function configuracion(): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ADMINISTRAR);

        return $this->json($this->configuracionArray($this->ajustes->actual()));
    }

    /** `{ recargoPorciento, ventaEnLinea, cierreMinutos }` */
    #[Route("/configuracion", name: "configuracion_guardar", methods: ["PUT"])]
    public function guardar(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ADMINISTRAR);
        $datos = $request->toArray();
        try {
            $config = $this->ajustes->guardar(
                $datos["recargoPorciento"] ?? "0",
                (bool) ($datos["ventaEnLinea"] ?? true),
                (int) ($datos["cierreMinutos"] ?? 60),
                $this->em->find(Usuario::class, $usuario->getId()),
            );
        } catch (VentaRechazada $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        }

        return $this->json($this->configuracionArray($config));
    }

    /** `?leidos=0|1` (sin él, todos), los más recientes primero. */
    #[Route("/mensajes", name: "mensajes", methods: ["GET"])]
    public function mensajes(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ADMINISTRAR);
        $criterio = $request->query->has("leidos") ? ["leido" => $request->query->getBoolean("leidos")] : [];
        $mensajes = $this->em->getRepository(MensajeContacto::class)->findBy($criterio, ["creadoEn" => "DESC"], 200);

        return $this->json([
            "items" => array_map(static fn(MensajeContacto $m) => [
                "id" => $m->getId(),
                "nombre" => $m->getNombre(),
                "email" => $m->getEmail(),
                "telefono" => $m->getTelefono(),
                "mensaje" => $m->getMensaje(),
                "idioma" => $m->getIdioma(),
                "creado" => $m->getCreadoEn()->format(DATE_ATOM),
                "leido" => $m->isLeido(),
            ], $mensajes),
            "sinLeer" => $this->em->getRepository(MensajeContacto::class)->count(["leido" => false]),
        ]);
    }

    /** `{ leido: bool }` */
    #[Route("/mensajes/{id<\d+>}", name: "mensaje", methods: ["PATCH"])]
    public function marcarMensaje(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ADMINISTRAR);
        $mensaje = $this->em->find(MensajeContacto::class, $id) ?? throw $this->createNotFoundException();
        $mensaje->marcarLeido((bool) ($request->toArray()["leido"] ?? true));
        $this->em->flush();

        return $this->json(["id" => $id, "leido" => $mensaje->isLeido()]);
    }

    /** @return array<string, mixed> */
    private function configuracionArray(ConfiguracionPagina $c): array
    {
        return [
            "recargoPorciento" => $c->getRecargoPorciento(),
            "ventaEnLinea" => $c->getVentaEnLinea(),
            "cierreMinutos" => $c->getCierreMinutos(),
            "actualizadaEn" => $c->getActualizadaEn()?->format(DATE_ATOM),
            "actualizadaPor" => $c->getActualizadaPor()?->getUsername(),
            "limites" => [
                "recargoMaximo" => Recargo::MAXIMO,
                "cierreMinimo" => AjustesPagina::CIERRE_MINIMO_MINUTOS,
                "cierreMaximo" => AjustesPagina::CIERRE_MAXIMO_MINUTOS,
            ],
        ];
    }
}
