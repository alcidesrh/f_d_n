<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Metadata\IriConverterInterface;
use App\Acceso\Capacidad;
use App\Acceso\Suplantacion\GestionSuplantacion;
use App\Acceso\Suplantacion\Suplantador;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Suplantación de usuarios (`App\Acceso\Suplantacion`): entrar como otro
 * usuario, volver a la cuenta propia y la configuración. Las reglas (solo el
 * administrador del sistema, nunca sobre otro, sin encadenar) las aplica
 * `Suplantador`; aquí solo la capacidad para la navegación.
 */
#[AsController]
final class SuplantacionController extends AbstractController
{
    public function __construct(
        private readonly Suplantador $suplantador,
        private readonly GestionSuplantacion $gestion,
        private readonly EntityManagerInterface $em,
    ) {}

    /** `{ motivo? }` → el Bearer para entrar como el usuario (como el login). */
    #[Route("/api/usuarios/{id<-?\d+>}/suplantar", name: "api_usuario_suplantar", methods: ["POST"])]
    public function suplantar(int $id, Request $request, #[CurrentUser] Usuario $actor, IriConverterInterface $iris): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::IAM_SUPLANTAR->value);
        $datos = $request->getContent() === "" ? [] : $request->toArray();
        $token = $this->suplantador->iniciar($actor, $id, is_string($datos["motivo"] ?? null) ? $datos["motivo"] : null, $request);
        $usuario = $token->getUsuario();

        return $this->json([
            "token" => $token->getToken(),
            "username" => $usuario?->getUsername(),
            "uri" => $usuario === null ? null : $iris->getIriFromResource($usuario),
            "expira" => $token->getExpira()?->format(\DATE_ATOM),
        ]);
    }

    /** Con el Bearer de suplantación: lo revoca. La app vuelve al token propio que guardó. */
    #[Route("/api/suplantacion/salir", name: "api_suplantacion_salir", methods: ["POST"])]
    public function salir(Request $request): JsonResponse
    {
        $this->suplantador->terminar($request);

        return $this->json(["salida" => true]);
    }

    #[Route("/api/suplantacion/configuracion", name: "api_suplantacion_configuracion", methods: ["GET"])]
    public function configuracion(): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::IAM_SUPLANTAR->value);

        return $this->json($this->gestion->datos());
    }

    #[Route("/api/suplantacion/configuracion", name: "api_suplantacion_configuracion_guardar", methods: ["PUT"])]
    public function guardar(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::IAM_SUPLANTAR->value);
        $this->gestion->guardar($request->toArray(), $this->em->find(Usuario::class, $usuario->getId()));

        return $this->json($this->gestion->datos());
    }
}
