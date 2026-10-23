<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ConfiguracionPagina;
use App\Entity\Empresa;
use App\Entity\MensajeContacto;
use App\Entity\PagoWeb;
use App\Entity\Usuario;
use App\Venta\ConsultaVenta;
use App\Venta\EnLinea\AjustesPagina;
use App\Venta\EnLinea\ConsultaComprasWeb;
use App\Venta\EnLinea\FiltroComprasWeb;
use App\Venta\EnLinea\GestionCompraWeb;
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
 * línea con filtro y calculadora, acciones sobre una compra (corregir el
 * comprador, posponer, reemitir la factura, reenviar el boleto; anular y
 * reasignar van por `/api/venta/boletos/*`), configuración (recargo, venta activa,
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

    /** `{ nombre, apellido?, email, telefono?, nit, nombreFactura? }`: corrige el comprador (la factura cambia al reemitirla). */
    #[Route("/compras/{id<\d+>}/comprador", name: "compra_comprador", methods: ["PUT"])]
    public function editarComprador(int $id, Request $request, GestionCompraWeb $gestion, ConsultaComprasWeb $consulta): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ADMINISTRAR);

        return $this->accion($id, $consulta, fn(PagoWeb $p) => $gestion->editarComprador($p, $request->toArray()));
    }

    /** `{ motivo }`: libera los asientos de los boletos emitidos; se reanuda reasignándolos. */
    #[Route("/compras/{id<\d+>}/posponer", name: "compra_posponer", methods: ["POST"])]
    public function posponer(int $id, Request $request, GestionCompraWeb $gestion, ConsultaComprasWeb $consulta): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ADMINISTRAR);

        return $this->accion($id, $consulta, fn(PagoWeb $p) => $gestion->posponer($p, (string) ($request->toArray()["motivo"] ?? "")));
    }

    /** Anula la factura de cada viaje en el certificador y certifica una nueva con los datos actuales. */
    #[Route("/compras/{id<\d+>}/factura", name: "compra_factura", methods: ["POST"])]
    public function reemitirFactura(int $id, GestionCompraWeb $gestion, ConsultaComprasWeb $consulta): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ADMINISTRAR);

        return $this->accion($id, $consulta, fn(PagoWeb $p) => $gestion->reemitirFactura($p));
    }

    /** `{ correo? }` (por defecto, el del comprador): reenvía el boleto de todos los viajes. */
    #[Route("/compras/{id<\d+>}/correo", name: "compra_correo", methods: ["POST"])]
    public function reenviar(int $id, Request $request, GestionCompraWeb $gestion, ConsultaComprasWeb $consulta): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ADMINISTRAR);
        $correo = $request->getContent() !== "" ? ($request->toArray()["correo"] ?? null) : null;

        return $this->accion($id, $consulta, fn(PagoWeb $p) => $gestion->reenviar($p, is_string($correo) ? $correo : null));
    }

    /** Empresas y estaciones para los filtros. */
    #[Route("/opciones", name: "opciones", methods: ["GET"])]
    public function opciones(ConsultaVenta $consulta, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::ADMINISTRAR);

        return $this->json([
            "empresas" => array_map(
                static fn(Empresa $e) => ["id" => $e->getId(), "nombre" => $e->getNombreCorto()],
                $this->em->getRepository(Empresa::class)->findBy([], ["nombre" => "ASC"]),
            ),
            "estaciones" => $consulta->estacionesEnLinea(),
            "departamento" => $usuario->getEstacion()?->getDepartamento(),
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

    /**
     * Ejecuta una acción sobre la compra y responde `{ resultado, compra }`
     * (la fila del listado, refrescada).
     *
     * @param callable(PagoWeb): mixed $accion
     */
    private function accion(int $id, ConsultaComprasWeb $consulta, callable $accion): JsonResponse
    {
        $pago = $this->em->find(PagoWeb::class, $id) ?? throw $this->createNotFoundException();
        try {
            $resultado = $accion($pago);
        } catch (VentaRechazada $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        }

        return $this->json([
            "resultado" => $resultado instanceof PagoWeb ? null : $resultado,
            "compra" => $consulta->una($id),
        ]);
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
