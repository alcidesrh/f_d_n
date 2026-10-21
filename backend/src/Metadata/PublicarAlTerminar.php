<?php

declare(strict_types=1);

namespace App\Metadata;

use App\Entity\Usuario;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

/**
 * Al terminar la petición (ya enviada la respuesta), el comando o el mensaje
 * que guardó configuración del cliente, la publica en una sola revisión
 * (modo automático) a nombre de quien la guardó. Si falla no rompe nada: se
 * registra y la siguiente publicación (o el despliegue) la incluye.
 */
final class PublicarAlTerminar
{
    public function __construct(
        private readonly ConfiguracionesTocadas $tocadas,
        private readonly PublicacionMetadata $publicacion,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {}

    #[AsEventListener(KernelEvents::TERMINATE)]
    #[AsEventListener(ConsoleEvents::TERMINATE)]
    #[AsEventListener(WorkerMessageHandledEvent::class)]
    public function publicar(): void
    {
        if (!$this->tocadas->hay()) {
            return;
        }
        $entidades = $this->tocadas->tomar();
        $usuario = $this->security->getUser();
        try {
            $this->publicacion->configuracionGuardada($entidades, $usuario instanceof Usuario ? $usuario : null);
        } catch (\Throwable $e) {
            $this->logger->error("No se pudo publicar la configuración guardada de {entidades}: {error}", [
                "entidades" => $entidades === null ? "todas" : implode(", ", $entidades),
                "error" => $e->getMessage(),
            ]);
        }
    }
}
