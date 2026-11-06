<?php

declare(strict_types=1);

namespace App\Acceso\Suplantacion;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Mientras se suplanta, corta lo que no se hace como otro
 * (`ReglasSuplantacion::bloqueo`): credenciales, cuenta, accesos y, según la
 * configuración, el chat y el correo. Va después del firewall (prioridad 8),
 * que es quien fija `SuplantacionActual`.
 */
final class BloqueoDuranteSuplantacion
{
    public function __construct(
        private readonly SuplantacionActual $actual,
        private readonly AjustesSuplantacion $ajustes,
        private readonly TokenStorageInterface $tokens,
    ) {}

    #[AsEventListener(KernelEvents::REQUEST, priority: 4)]
    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        // El firewall es perezoso: leer el token fuerza la autenticación por Bearer.
        $this->tokens->getToken();
        if (!$this->actual->activa()) {
            return;
        }
        $request = $event->getRequest();
        $ruta = $request->getPathInfo();
        $chat = $this->ajustes->actual()->getChat();
        $respuesta = match (ReglasSuplantacion::bloqueo($request->getMethod(), $ruta, $chat)) {
            Bloqueo::NINGUNO => null,
            Bloqueo::IGNORAR => new Response(null, Response::HTTP_NO_CONTENT),
            Bloqueo::PROHIBIDO => new JsonResponse(["error" => ReglasSuplantacion::mensaje($ruta, $chat), "codigo" => "suplantacion"], Response::HTTP_FORBIDDEN),
        };
        if ($respuesta !== null) {
            $event->setResponse($respuesta);
        }
    }
}
