<?php

declare(strict_types=1);

namespace App\Sincronizacion\Nuevo;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Lo que una petición dejó en `sync_cambio` se lleva al legado al terminar,
 * ya enviada la respuesta: sin esperar al ciclo de cada minuto.
 */
final class AlTerminar
{
    public function __construct(
        private readonly VigilanteCambios $vigilante,
        private readonly ExportadorCambios $exportador,
        private readonly LoggerInterface $logger,
    ) {}

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    public function __invoke(TerminateEvent $event): void
    {
        if (!$this->vigilante->hayPendientes()) {
            return;
        }
        try {
            $this->exportador->procesar(50);
        } catch (\Throwable $e) {
            // El ciclo lo reintenta.
            $this->logger->warning("Sincronización: al terminar la petición: {error}", ["error" => $e->getMessage()]);
        }
    }
}
