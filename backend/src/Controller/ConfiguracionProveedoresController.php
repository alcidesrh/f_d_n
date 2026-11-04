<?php

declare(strict_types=1);

namespace App\Controller;

use App\Acceso\Capacidad;
use App\Correo\Configuracion\GestionCorreo;
use App\Entity\Usuario;
use App\Venta\Facturacion\Forcon\GestionForcon;
use App\Venta\Pago\Cybersource\GestionCybersource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Configuración del certificador FEL (Forcon) y de la pasarela de pago
 * (Cybersource) desde la administración (ADR-047). Capacidad
 * `config.proveedores`. La clave global de Forcon nunca sale: solo `conClave`.
 *
 * - `GET|PUT /api/configuracion-proveedores/forcon`, `POST …/forcon/probar` (`{ nit, establecimiento }`).
 * - `GET|PUT /api/configuracion-proveedores/cybersource`.
 * - `GET|PUT /api/configuracion-proveedores/correo`, `POST …/correo/probar` (`{ dominio, para }`), ADR-048.
 *   Los tokens de MailPace tampoco salen: solo si cada dominio tiene uno.
 */
#[AsController]
#[Route("/api/configuracion-proveedores", name: "api_configuracion_proveedores_")]
final class ConfiguracionProveedoresController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GestionForcon $forcon,
        private readonly GestionCybersource $cybersource,
        private readonly GestionCorreo $correo,
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
        $this->forcon->guardar($request->toArray(), $this->em->find(Usuario::class, $usuario->getId()));

        return $this->json($this->forcon->datos());
    }

    #[Route("/forcon/probar", name: "forcon_probar", methods: ["POST"])]
    public function probarForcon(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::CONFIG_PROVEEDORES->value);
        $datos = $request->toArray();
        return $this->json($this->forcon->probar((string) ($datos["nit"] ?? ""), (int) ($datos["establecimiento"] ?? 0)));
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
        $this->cybersource->guardar($request->toArray(), $this->em->find(Usuario::class, $usuario->getId()));

        return $this->json($this->cybersource->datos());
    }

    #[Route("/correo", name: "correo", methods: ["GET"])]
    public function correo(): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::CONFIG_PROVEEDORES->value);

        return $this->json($this->correo->datos($this->webhook()));
    }

    /** `{ activo?, servidor?, puerto?, dominiosEnvio?: [{ dominio, token }], dominio?, dominioEntrada?, claveWebhook?, remitenteBoletos?, remitenteReportes?, buzonContacto? }` */
    #[Route("/correo", name: "correo_guardar", methods: ["PUT"])]
    public function guardarCorreo(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::CONFIG_PROVEEDORES->value);
        $this->correo->guardar($request->toArray(), $this->em->find(Usuario::class, $usuario->getId()));

        return $this->json($this->correo->datos($this->webhook()));
    }

    #[Route("/correo/probar", name: "correo_probar", methods: ["POST"])]
    public function probarCorreo(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::CONFIG_PROVEEDORES->value);
        $datos = $request->toArray();
        $this->correo->probar((string) ($datos["dominio"] ?? ""), (string) ($datos["para"] ?? ""));

        return $this->json(["ok" => true]);
    }

    /** La URL que se pega en MailPace (Domain → Inbound → Webhook). */
    private function webhook(): string
    {
        return $this->generateUrl("api_correo_entrante_mailpace", [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
