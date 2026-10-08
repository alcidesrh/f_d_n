<?php

declare(strict_types=1);

namespace App\Controller;

use App\Chat\Archivos;
use App\Chat\AvisosChat;
use App\Chat\ChatRechazado;
use App\Chat\Conversaciones;
use App\Chat\Tarjeta\Tarjetas;
use App\Entity\ChatArchivo;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Chat interno (ADR-026). Cualquier usuario con sesión lo usa; con quién
 * habla lo decide `App\Chat\Directorio` y qué ve de lo compartido, sus
 * propios permisos.
 */
#[AsController]
#[Route("/api/chat", name: "api_chat_")]
final class ChatController extends AbstractController
{
    public function __construct(
        private readonly Conversaciones $chat,
        private readonly AvisosChat $avisos,
        private readonly Archivos $archivos,
        private readonly EntityManagerInterface $em,
        private readonly Tarjetas $tarjetas,
    ) {}

    /** Token de suscripción a los avisos privados del usuario (Mercure). */
    #[Route("/token", name: "token", methods: ["GET"])]
    public function token(#[CurrentUser] Usuario $yo): JsonResponse
    {
        return $this->responder(fn() => $this->avisos->token((int) $yo->getId()));
    }

    #[Route("/contactos", name: "contactos", methods: ["GET"])]
    public function contactos(#[CurrentUser] Usuario $yo): JsonResponse
    {
        return $this->json($this->chat->contactos($yo));
    }

    /** Tipos de registro que el usuario puede adjuntar (selector de registros). */
    #[Route("/recursos", name: "recursos", methods: ["GET"])]
    public function recursos(): JsonResponse
    {
        return $this->json($this->tarjetas->compartibles());
    }

    #[Route("/canales", name: "bandeja", methods: ["GET"])]
    public function bandeja(#[CurrentUser] Usuario $yo): JsonResponse
    {
        return $this->json($this->chat->bandeja($yo));
    }

    /** `{ usuario }` → la conversación directa (la crea si no existe). */
    #[Route("/canales/directo", name: "directo", methods: ["POST"])]
    public function directo(Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        return $this->responder(fn() => $this->chat->abrirDirecto($yo, (int) ($request->toArray()["usuario"] ?? 0)));
    }

    /** `{ nombre, miembros: [ids] }` */
    #[Route("/canales/grupo", name: "grupo", methods: ["POST"])]
    public function grupo(Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        $datos = $request->toArray();

        return $this->responder(fn() => $this->chat->crearGrupo($yo, (string) ($datos["nombre"] ?? ""), self::ids($datos["miembros"] ?? [])));
    }

    /** `?antes=id` (anteriores) o `?despues=id` (nuevos). */
    #[Route("/canales/{id<\d+>}/mensajes", name: "mensajes", methods: ["GET"])]
    public function mensajes(int $id, Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        return $this->responder(fn() => $this->chat->mensajes(
            $this->chat->canal($id, $yo),
            $request->query->has("antes") ? $request->query->getInt("antes") : null,
            $request->query->has("despues") ? $request->query->getInt("despues") : null,
        ));
    }

    /** `{ texto, adjuntos?: [{ tipo, id }], archivos?: [ids], respuestaA?: idMensaje }` */
    #[Route("/canales/{id<\d+>}/mensajes", name: "escribir", methods: ["POST"])]
    public function escribir(int $id, Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        $datos = $request->toArray();

        return $this->responder(fn() => $this->chat->escribir(
            $this->chat->canal($id, $yo),
            $yo,
            (string) ($datos["texto"] ?? ""),
            $datos["adjuntos"] ?? [],
            self::ids($datos["archivos"] ?? []),
            isset($datos["respuestaA"]) ? (int) $datos["respuestaA"] : null,
        ));
    }

    /** Multipart, campo `archivo`. Queda suelto hasta que un mensaje lo use. */
    #[Route("/archivos", name: "subir", methods: ["POST"])]
    public function subir(Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        return $this->responder(function () use ($request, $yo) {
            $archivo = $request->files->get("archivo") ?? throw new ChatRechazado("Falta el archivo.");

            return $this->archivos->presentar($this->archivos->subir($archivo, $yo));
        });
    }

    /**
     * Contenido de un archivo por URL firmada (pública: la usan `<img>` y los
     * enlaces de descarga, que no llevan Bearer). `?descargar=1` lo baja.
     */
    #[Route("/archivos/{id<\d+>}/{firma<[0-9a-f]{32}>}", name: "archivo", methods: ["GET"])]
    public function archivo(int $id, string $firma, Request $request): Response
    {
        $archivo = $this->archivos->firmaValida($id, $firma, $request->query->getInt("exp")) ? $this->em->find(ChatArchivo::class, $id) : null;
        $ruta = $archivo ? $this->archivos->absoluta($archivo->getRuta()) : null;
        if ($ruta === null || !is_file($ruta)) {
            return new Response("Archivo no disponible.", Response::HTTP_NOT_FOUND);
        }
        $enLinea = !$request->query->getBoolean("descargar") && ($archivo->esImagen() || $archivo->getTipo() === "application/pdf");
        $respuesta = new BinaryFileResponse($ruta, headers: ["Content-Type" => $archivo->getTipo()]);
        $respuesta->setContentDisposition($enLinea ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT, $archivo->getNombre(), "archivo");
        $respuesta->setPrivate();
        $respuesta->setMaxAge(6 * 3600);
        $respuesta->headers->set("X-Content-Type-Options", "nosniff");
        $respuesta->headers->set("Content-Security-Policy", "sandbox; default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");

        return $respuesta;
    }

    /** `{ hasta: idMensaje }` */
    #[Route("/canales/{id<\d+>}/leido", name: "leido", methods: ["POST"])]
    public function leido(int $id, Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        return $this->responder(function () use ($id, $request, $yo) {
            $this->chat->marcarLeido($this->chat->canal($id, $yo), $yo, (int) ($request->toArray()["hasta"] ?? 0));

            return ["ok" => true];
        });
    }

    /** `{ usuarios: [ids], canales: [ids], texto?, adjuntos: [{ tipo, id }] }` — "Enviar por chat". */
    #[Route("/compartir", name: "compartir", methods: ["POST"])]
    public function compartir(Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        $datos = $request->toArray();

        return $this->responder(fn() => ["canales" => $this->chat->compartir(
            $yo,
            self::ids($datos["usuarios"] ?? []),
            self::ids($datos["canales"] ?? []),
            (string) ($datos["texto"] ?? ""),
            $datos["adjuntos"] ?? [],
        )]);
    }

    /** @return list<int> */
    private static function ids(mixed $valor): array
    {
        return is_array($valor) ? array_values(array_filter(array_map("intval", $valor), static fn(int $id) => $id > 0)) : [];
    }

    private function responder(callable $operacion): JsonResponse
    {
        try {
            return $this->json($operacion());
        } catch (ChatRechazado $e) {
            return $this->json(["error" => $e->getMessage()], $e->estado);
        }
    }
}
