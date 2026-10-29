<?php

declare(strict_types=1);

namespace App\Controller;

use App\Acceso\Capacidad;
use App\Chat\ChatRechazado;
use App\Chat\Predeterminado\Despacho;
use App\Chat\Predeterminado\Difusion;
use App\Chat\Predeterminado\MensajesPredeterminados;
use App\Entity\MensajePredeterminado;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Mensajes predeterminados del chat (ADR-045): las instancias que mandan
 * solas a los avisos del sistema de su audiencia. Solo con
 * `chat.predeterminados` (los roles `ROLE_SUPER_ADMIN` y `ROLE_ADMIN`).
 *
 * - `GET /api/chat/predeterminados` → `{ mensajes, opciones }`.
 * - `POST …`, `PUT …/{id}`, `DELETE …/{id}`: crear, editar, eliminar.
 * - `POST …/{id}/activo` `{ activo }`: activar o desactivar.
 * - `POST …/{id}/probar`: lo que saldría ahora, solo a quien prueba.
 */
#[AsController]
#[Route("/api/chat/predeterminados", name: "api_chat_predeterminados_")]
final class MensajePredeterminadoController extends AbstractController
{
    public function __construct(
        private readonly MensajesPredeterminados $mensajes,
        private readonly Despacho $despacho,
        private readonly Difusion $difusion,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $reloj,
    ) {}

    #[Route("", name: "listar", methods: ["GET"])]
    public function listar(): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::CHAT_PREDETERMINADOS->value);

        return $this->json(["mensajes" => $this->mensajes->listar(), "opciones" => $this->mensajes->opciones()]);
    }

    #[Route("", name: "crear", methods: ["POST"])]
    public function crear(Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        $this->denyAccessUnlessGranted(Capacidad::CHAT_PREDETERMINADOS->value);

        return $this->responder(fn() => $this->mensajes->presentar($this->mensajes->crear($request->toArray(), $yo)), 201);
    }

    #[Route("/{id<\d+>}", name: "editar", methods: ["PUT"])]
    public function editar(int $id, Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        $m = $this->instancia($id);

        return $this->responder(fn() => $this->mensajes->presentar($this->mensajes->editar($m, $request->toArray(), $yo)));
    }

    #[Route("/{id<\d+>}/activo", name: "activo", methods: ["POST"])]
    public function activo(int $id, Request $request): JsonResponse
    {
        $m = $this->instancia($id);

        return $this->json($this->mensajes->presentar($this->mensajes->activar($m, (bool) ($request->toArray()["activo"] ?? false))));
    }

    #[Route("/{id<\d+>}", name: "eliminar", methods: ["DELETE"])]
    public function eliminar(int $id): JsonResponse
    {
        $this->mensajes->eliminar($this->instancia($id));

        return $this->json(null, 204);
    }

    #[Route("/{id<\d+>}/probar", name: "probar", methods: ["POST"])]
    public function probar(int $id, #[CurrentUser] Usuario $yo): JsonResponse
    {
        $m = $this->instancia($id);

        return $this->responder(function () use ($m, $yo) {
            $this->difusion->probar($m, $this->despacho->muestra($m, \DateTimeImmutable::createFromInterface($this->reloj->now())), $yo);

            return ["enviado" => true];
        });
    }

    private function instancia(int $id): MensajePredeterminado
    {
        $this->denyAccessUnlessGranted(Capacidad::CHAT_PREDETERMINADOS->value);

        return $this->em->find(MensajePredeterminado::class, $id) ?? throw $this->createNotFoundException();
    }

    private function responder(callable $operacion, int $estado = 200): JsonResponse
    {
        try {
            return $this->json($operacion(), $estado);
        } catch (ChatRechazado $e) {
            return $this->json(["error" => $e->getMessage()], $e->estado);
        }
    }
}
