<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Página web pública (ADR-021, ADR-023): SPA de `pagina/` compilada en
 * `public/pagina/`. Los archivos estáticos los sirve el servidor web; aquí
 * llegan las rutas de la SPA:
 *
 * - `/pagina` y `/pagina/` redirigen al idioma del navegador (`/pagina/es/`…).
 * - Si la ruta tiene HTML prerenderizado (`public/pagina/<ruta>/index.html`,
 *   las páginas públicas en cada idioma) se entrega ese: los buscadores
 *   leen el contenido sin ejecutar JS.
 * - Si no (pago, compra…), el `index.html` de la SPA.
 */
#[AsController]
final class PaginaController
{
    public const IDIOMAS = ["es", "en", "fr", "de", "it"];

    public function __construct(
        #[Autowire("%kernel.project_dir%/public/pagina")]
        private readonly string $raiz,
    ) {}

    #[Route("/pagina/{ruta}", name: "pagina", requirements: ["ruta" => ".*"], defaults: ["ruta" => ""], methods: ["GET"], priority: -10)]
    public function __invoke(Request $request, string $ruta): Response
    {
        $ruta = trim($ruta, "/");
        if ($ruta === "") {
            $idioma = $request->getPreferredLanguage(self::IDIOMAS) ?? self::IDIOMAS[0];

            return new RedirectResponse(
                sprintf("/pagina/%s/%s", $idioma, $request->getQueryString() ? "?" . $request->getQueryString() : ""),
                Response::HTTP_FOUND,
                ["Vary" => "Accept-Language", "Cache-Control" => "no-cache"],
            );
        }

        $archivo = $this->prerenderizado($ruta) ?? $this->raiz . "/index.html";
        if (!is_file($archivo)) {
            return new Response(
                "La página web no está compilada: ejecute `npm run build` en pagina/.",
                Response::HTTP_NOT_FOUND,
                ["Content-Type" => "text/plain; charset=utf-8"],
            );
        }

        // Response (no BinaryFileResponse): el HTML es chico y así la barra
        // de depuración de Symfony puede inyectarse en desarrollo.
        return new Response((string) file_get_contents($archivo), Response::HTTP_OK, [
            "Content-Type" => "text/html; charset=utf-8",
            "Cache-Control" => "no-cache",
        ]);
    }

    /** HTML prerenderizado de la ruta (solo segmentos simples: nada de `..`). */
    private function prerenderizado(string $ruta): ?string
    {
        if (!preg_match('#^[a-z]{2}(/[a-z0-9-]+)*$#', $ruta)) {
            return null;
        }
        $archivo = "{$this->raiz}/{$ruta}/index.html";

        return is_file($archivo) ? $archivo : null;
    }
}
