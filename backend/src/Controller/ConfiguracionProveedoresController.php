<?php

declare(strict_types=1);

namespace App\Controller;

use App\Acceso\Capacidad;
use App\Entity\Usuario;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Facturacion\Forcon\GestionForcon;
use App\Venta\Pago\Cybersource\GestionCybersource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Configuración del certificador FEL (Forcon) y de la pasarela de pago
 * (Cybersource) desde la administración (ADR-047). Capacidad
 * `config.proveedores`. La clave global de Forcon nunca sale: solo `conClave`.
 *
 * - `GET|PUT /api/configuracion-proveedores/forcon`, `POST …/forcon/probar` (`{ nit, establecimiento }`).
 * - `GET|PUT /api/configuracion-proveedores/cybersource`.
 */
#[AsController]
#[Route("/api/configuracion-proveedores", name: "api_configuracion_proveedores_")]
final class ConfiguracionProveedoresController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GestionForcon $forcon,
        private readonly GestionCybersource $cybersource,
    ) {}

    #[Route("/forcon", name: "forcon", methods: ["GET"])]
    public function forcon(): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::CONFIG_PROVEEDORES->value);

        return $this->json($this->forcon->datos());
    }

    /** `{ activo?, url?, usuario?, clave?, emisoresEnLinea?: [{ empresa, emisor }] }` */
    #[Route("/forcon", name: "forcon_guardar", methods: ["PUT"])]
    public function guardarForcon(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::CONFIG_PROVEEDORES->value);
        try {
            $this->forcon->guardar($request->toArray(), $this->em->find(Usuario::class, $usuario->getId()));
        } catch (VentaRechazada $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        }

        return $this->json($this->forcon->datos());
    }

    #[Route("/forcon/probar", name: "forcon_probar", methods: ["POST"])]
    public function probarForcon(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::CONFIG_PROVEEDORES->value);
        $datos = $request->toArray();
        try {
            return $this->json($this->forcon->probar((string) ($datos["nit"] ?? ""), (int) ($datos["establecimiento"] ?? 0)));
        } catch (VentaRechazada $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        }
    }

    #[Route("/cybersource", name: "cybersource", methods: ["GET"])]
    public function cybersource(): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::CONFIG_PROVEEDORES->value);

        return $this->json($this->cybersource->datos());
    }

    /** `{ activo?, url?, orgId? }` */
    #[Route("/cybersource", name: "cybersource_guardar", methods: ["PUT"])]
    public function guardarCybersource(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::CONFIG_PROVEEDORES->value);
        try {
            $this->cybersource->guardar($request->toArray(), $this->em->find(Usuario::class, $usuario->getId()));
        } catch (VentaRechazada $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        }

        return $this->json($this->cybersource->datos());
    }
}
