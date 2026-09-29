<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Página web pública (ADR-021): SPA de `pagina/` compilada en
 * `public/pagina/`. Los archivos estáticos los sirve el servidor web; toda
 * otra ruta bajo `/pagina` devuelve su `index.html` (rutas del cliente).
 */
#[AsController]
final class PaginaController
{
    public function __construct(
        #[Autowire("%kernel.project_dir%/public/pagina/index.html")]
        private readonly string $indice,
    ) {}

    #[Route("/pagina/{ruta}", name: "pagina", requirements: ["ruta" => ".*"], defaults: ["ruta" => ""], methods: ["GET"], priority: -10)]
    public function __invoke(): Response
    {
        if (!is_file($this->indice)) {
            return new Response(
                "La página web no está compilada: ejecute `npm run build` en pagina/.",
                Response::HTTP_NOT_FOUND,
                ["Content-Type" => "text/plain; charset=utf-8"],
            );
        }

        $respuesta = new BinaryFileResponse($this->indice);
        $respuesta->headers->set("Content-Type", "text/html; charset=utf-8");
        $respuesta->headers->set("Cache-Control", "no-cache");

        return $respuesta;
    }
}
