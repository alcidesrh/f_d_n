<?php

declare(strict_types=1);

namespace App\Chat\Predeterminado\Tarea;

use App\Chat\Predeterminado\Despacho;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class EnviarMensajesPredeterminadosHandler
{
    public function __construct(
        private readonly Despacho $despacho,
        private readonly ClockInterface $reloj,
    ) {}

    /** @return array{envios: int, destinatarios: int, fallidos: int} */
    public function __invoke(EnviarMensajesPredeterminados $mensaje): array
    {
        return $this->despacho->pendientes(\DateTimeImmutable::createFromInterface($this->reloj->now()));
    }
}
