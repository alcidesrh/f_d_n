<?php

declare(strict_types=1);

namespace App\Metadata;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * `X-Metadata-Revision` en cada respuesta de la API a un usuario con sesión:
 * un navegador sin Mercure (hub caído, conexión cortada al desplegar) se
 * entera de una revisión nueva en su próxima petición, sin pedir nada más.
 */
#[AsEventListener(KernelEvents::RESPONSE)]
final class CabeceraRevision
{
    public const NOMBRE = "X-Metadata-Revision";

    public function __construct(
        private readonly RegistroRevisiones $registro,
        private readonly Security $security,
    ) {}

    public function __invoke(ResponseEvent $evento): void
    {
        if (!$evento->isMainRequest()) {
            return;
        }
        $ruta = $evento->getRequest()->getPathInfo();
        if ((!str_starts_with($ruta, "/api/") && !str_starts_with($ruta, "/graphql")) || $this->security->getUser() === null) {
            return;
        }
        try {
            $numero = $this->registro->numeroVigente();
        } catch (\Throwable) {
            // Sin la tabla (base sin migrar) la respuesta sale igual.
            return;
        }
        if ($numero > 0) {
            $evento->getResponse()->headers->set(self::NOMBRE, (string) $numero);
        }
    }
}
