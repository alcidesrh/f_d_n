<?php

declare(strict_types=1);

namespace App\Controller;

use App\Acceso\Capacidad;
use App\Chat\Conversaciones;
use App\Correo\Adjuntos;
use App\Correo\Borradores;
use App\Correo\Buzon;
use App\Correo\CorreoRechazado;
use App\Correo\Cuentas;
use App\Correo\Entrante\Mailpace;
use App\Correo\Entrante\Recepcion;
use App\Correo\Filtro;
use App\Entity\CorreoAdjunto;
use App\Entity\CorreoCuenta;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Correo de la empresa (`{usuario}@{CORREO_DOMINIO}`), al lado del chat.
 * Cualquier usuario con sesión tiene su buzón; escribir fuera de la empresa
 * pide `correo.externo`. El correo de fuera entra por el webhook de MailPace
 * (público, firmado).
 */
#[AsController]
#[Route("/api/correo", name: "api_correo_")]
final class CorreoController extends AbstractController
{
    public function __construct(
        private readonly Cuentas $cuentas,
        private readonly Buzon $buzon,
        private readonly Borradores $borradores,
        private readonly Adjuntos $adjuntos,
        private readonly Conversaciones $chat,
        private readonly EntityManagerInterface $em,
    ) {}

    /** La cuenta del usuario y los contadores de cada carpeta. */
    #[Route("", name: "estado", methods: ["GET"])]
    public function estado(#[CurrentUser] Usuario $yo): JsonResponse
    {
        return $this->json($this->buzon->estado($this->cuenta($yo), $this->puedeExterno()));
    }

    /** `?carpeta=entrada|enviados|borradores|papelera|destacados&noLeidos=1&adjuntos=1&origen=internos|externos&q=&antes=id` */
    #[Route("/correos", name: "listar", methods: ["GET"])]
    public function listar(Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        return $this->json($this->buzon->listar($this->cuenta($yo), Filtro::deQuery($request->query->all())));
    }

    /** El correo completo. `?leer=1` además lo marca leído. */
    #[Route("/correos/{id<\d+>}", name: "ver", methods: ["GET"])]
    public function ver(int $id, Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        return $this->responder(function () use ($id, $request, $yo) {
            $cuenta = $this->cuenta($yo);
            $m = $this->buzon->correo($cuenta, $id);
            if ($request->query->getBoolean("leer") && !$m->isLeido()) {
                $this->buzon->aplicar($cuenta, [$id], "leido");
            }

            return $this->buzon->detalle($m);
        });
    }

    /** `{ ids: [], accion: leido|noLeido|destacar|quitarDestacado|papelera|restaurar|eliminar }` → `{ n }` */
    #[Route("/correos/acciones", name: "acciones", methods: ["POST"])]
    public function acciones(Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        $datos = $request->toArray();

        return $this->responder(fn() => ["n" => $this->buzon->aplicar($this->cuenta($yo), self::ids($datos["ids"] ?? []), (string) ($datos["accion"] ?? ""))]);
    }

    #[Route("/papelera/vaciar", name: "vaciar", methods: ["POST"])]
    public function vaciar(#[CurrentUser] Usuario $yo): JsonResponse
    {
        return $this->json(["n" => $this->buzon->vaciarPapelera($this->cuenta($yo))]);
    }

    #[Route("/correos/{id<\d+>}/reintentar", name: "reintentar", methods: ["POST"])]
    public function reintentar(int $id, #[CurrentUser] Usuario $yo): JsonResponse
    {
        return $this->responder(function () use ($id, $yo) {
            $m = $this->buzon->correo($this->cuenta($yo), $id);
            $this->borradores->reintentar($m);

            return $this->buzon->detalle($m);
        });
    }

    /**
     * Borrador nuevo: `{ para?, cc?, cco?, asunto?, texto?, origen?: id, modo?: responder|responderTodos|reenviar }`.
     * Con `origen`, la respuesta o el reenvío ya armados.
     */
    #[Route("/borradores", name: "borrador_crear", methods: ["POST"])]
    public function crearBorrador(Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        $datos = $request->toArray();

        return $this->responder(function () use ($datos, $yo) {
            $cuenta = $this->cuenta($yo);
            $origen = isset($datos["origen"]) ? $this->buzon->correo($cuenta, (int) $datos["origen"]) : null;

            return $this->buzon->detalle($this->borradores->crear($cuenta, $datos, $origen, isset($datos["modo"]) ? (string) $datos["modo"] : null));
        });
    }

    /** Guardado mientras se escribe: `{ para, cc, cco, asunto, texto }` (lo que venga) → `{ id, fecha }`. */
    #[Route("/borradores/{id<\d+>}", name: "borrador_guardar", methods: ["PUT"])]
    public function guardarBorrador(int $id, Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        return $this->responder(function () use ($id, $request, $yo) {
            $b = $this->borradores->guardar($this->buzon->correo($this->cuenta($yo), $id), $request->toArray());

            return ["id" => $b->getId(), "fecha" => $b->getFecha()->format(\DateTimeInterface::ATOM)];
        });
    }

    #[Route("/borradores/{id<\d+>}", name: "borrador_descartar", methods: ["DELETE"])]
    public function descartarBorrador(int $id, #[CurrentUser] Usuario $yo): JsonResponse
    {
        return $this->responder(function () use ($id, $yo) {
            $this->borradores->descartar($this->buzon->correo($this->cuenta($yo), $id));

            return null;
        });
    }

    /** Lo último escrito puede venir en el cuerpo (como al guardar). */
    #[Route("/borradores/{id<\d+>}/enviar", name: "borrador_enviar", methods: ["POST"])]
    public function enviar(int $id, Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        return $this->responder(function () use ($id, $request, $yo) {
            $b = $this->buzon->correo($this->cuenta($yo), $id);
            $datos = $request->getContent() !== "" ? $request->toArray() : [];
            if ($datos !== []) {
                $this->borradores->guardar($b, $datos);
            }

            return $this->buzon->detalle($this->borradores->enviar($b, $this->puedeExterno()));
        });
    }

    /** Multipart, campo `archivo`. */
    #[Route("/borradores/{id<\d+>}/adjuntos", name: "borrador_adjuntar", methods: ["POST"])]
    public function adjuntar(int $id, Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        return $this->responder(fn() => $this->borradores->adjuntar(
            $this->buzon->correo($this->cuenta($yo), $id),
            $request->files->get("archivo") ?? throw new CorreoRechazado("Falta el archivo."),
        ));
    }

    #[Route("/borradores/{id<\d+>}/adjuntos/{adjunto<\d+>}", name: "borrador_quitar_adjunto", methods: ["DELETE"])]
    public function quitarAdjunto(int $id, int $adjunto, #[CurrentUser] Usuario $yo): JsonResponse
    {
        return $this->responder(function () use ($id, $adjunto, $yo) {
            $this->borradores->quitarAdjunto($this->buzon->correo($this->cuenta($yo), $id), $adjunto);

            return null;
        });
    }

    /** `?q=` → cuentas de la casa con quienes puede hablar y direcciones de fuera ya usadas. */
    #[Route("/contactos", name: "contactos", methods: ["GET"])]
    public function contactos(Request $request, #[CurrentUser] Usuario $yo): JsonResponse
    {
        return $this->json($this->buzon->contactos(
            $this->cuenta($yo),
            (string) $request->query->get("q", ""),
            fn(array $ids) => $this->chat->conversables($yo, $ids),
            $this->puedeExterno(),
        ));
    }

    /**
     * Un adjunto por URL firmada (pública: la usan las `<img>` del correo y
     * los enlaces de descarga, sin Bearer). `?descargar=1` lo baja.
     */
    #[Route("/adjuntos/{id<\d+>}/{firma<[0-9a-f]{32}>}", name: "adjunto", methods: ["GET"])]
    public function adjunto(int $id, string $firma, Request $request): Response
    {
        $a = $this->adjuntos->firmaValida($id, $firma, $request->query->getInt("exp")) ? $this->em->find(CorreoAdjunto::class, $id) : null;
        $ruta = $a !== null ? $this->adjuntos->absoluta($a->getRuta()) : null;
        if ($ruta === null || !is_file($ruta)) {
            return new Response("Archivo no disponible.", Response::HTTP_NOT_FOUND);
        }
        $enLinea = !$request->query->getBoolean("descargar") && Adjuntos::enLineaSegura($a->getTipo());
        $respuesta = new BinaryFileResponse($ruta, headers: ["Content-Type" => $enLinea ? $a->getTipo() : "application/octet-stream"]);
        $respuesta->setContentDisposition($enLinea ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT, $a->getNombre(), "archivo");
        $respuesta->setPrivate();
        $respuesta->setMaxAge(6 * 3600);
        $respuesta->headers->set("X-Content-Type-Options", "nosniff");
        $respuesta->headers->set("Content-Security-Policy", "sandbox; default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");

        return $respuesta;
    }

    /**
     * Webhook de correo entrante de MailPace (público). Sin clave configurada
     * o con firma inválida: 401 (MailPace reintenta y luego rebota). Un
     * correo sin destinatarios de la casa se acepta y se descarta.
     */
    #[Route("/entrante/mailpace", name: "entrante_mailpace", methods: ["POST"])]
    public function entranteMailpace(
        Request $request,
        Recepcion $recepcion,
        LoggerInterface $logger,
        #[Autowire(env: "default::CORREO_MAILPACE_CLAVE")] ?string $clave = null,
    ): JsonResponse {
        $cuerpo = $request->getContent();
        if (!Mailpace::firmaValida($cuerpo, $request->headers->get(Mailpace::CABECERA_FIRMA), (string) $clave)) {
            $logger->warning("Webhook de MailPace con firma inválida o sin CORREO_MAILPACE_CLAVE.");

            return $this->json(["error" => "Firma inválida."], Response::HTTP_UNAUTHORIZED);
        }
        try {
            $datos = json_decode($cuerpo, true, 64, JSON_THROW_ON_ERROR);
            $correo = Mailpace::leer(is_array($datos) ? $datos : []);
        } catch (\JsonException|\InvalidArgumentException $e) {
            $logger->warning("Correo entrante ilegible: {error}", ["error" => $e->getMessage()]);

            return $this->json(["error" => "Correo ilegible."], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(["entregado" => $recepcion->recibir($correo)]);
    }

    private function cuenta(Usuario $yo): CorreoCuenta
    {
        return $this->cuentas->de($yo);
    }

    private function puedeExterno(): bool
    {
        return $this->isGranted(Capacidad::CORREO_EXTERNO->value);
    }

    /** @return list<int> */
    private static function ids(mixed $valor): array
    {
        return is_array($valor) ? array_values(array_filter(array_map("intval", $valor), static fn(int $id) => $id > 0)) : [];
    }

    private function responder(callable $operacion): JsonResponse
    {
        try {
            $r = $operacion();

            return $r === null ? $this->json(null, Response::HTTP_NO_CONTENT) : $this->json($r);
        } catch (CorreoRechazado $e) {
            return $this->json(["error" => $e->getMessage()], $e->estado);
        }
    }
}
