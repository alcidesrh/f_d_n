<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Usuario;
use App\Metadata\PublicacionMetadata;
use App\Metadata\PublicacionRechazada;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Metadata que el frontend guarda en el navegador (ADR-036). El manifiesto
 * lo lee cualquier usuario con sesión; ver lo pendiente y publicar a mano
 * pide `config.editar`.
 */
#[Route("/api/metadata", name: "api_metadata_")]
final class MetadataController extends AbstractController
{
    public const PUBLICAR = "config.editar";

    public function __construct(private readonly PublicacionMetadata $publicacion) {}

    /**
     * `{ revision, esquema: { revision, huella }, configs: { Entidad: revision }, completa, aviso }`.
     * La etiqueta es la revisión: el navegador revalida y recibe 304 si nada cambió.
     */
    #[Route("", name: "manifiesto", methods: ["GET"])]
    public function manifiesto(Request $request): Response
    {
        $estado = $this->publicacion->vigente();
        $respuesta = new JsonResponse($estado->manifiesto());
        $respuesta->setEtag("r" . $estado->revision);
        $respuesta->setPrivate();
        $respuesta->headers->addCacheControlDirective("no-cache");
        $respuesta->isNotModified($request);

        return $respuesta;
    }

    /** `{ modo, revision, pendiente: { esquema, agregadas, modificadas, eliminadas }, historial }` */
    #[Route("/publicacion", name: "publicacion", methods: ["GET"])]
    public function publicacion(): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::PUBLICAR);

        return $this->json($this->publicacion->resumen());
    }

    /** `{ nota?, aviso?, completa? }`; responde como `GET /publicacion`. */
    #[Route("/publicacion", name: "publicar", methods: ["POST"])]
    public function publicar(Request $request, #[CurrentUser] Usuario $usuario): JsonResponse
    {
        $this->denyAccessUnlessGranted(self::PUBLICAR);
        $datos = $request->toArray();
        try {
            $this->publicacion->publicar(
                $usuario,
                is_string($datos["nota"] ?? null) ? $datos["nota"] : null,
                ($datos["aviso"] ?? false) === true,
                ($datos["completa"] ?? false) === true,
            );
        } catch (PublicacionRechazada $e) {
            return $this->json($e->toArray(), $e->estadoHttp);
        }

        return $this->json($this->publicacion->resumen(), 201);
    }
}
