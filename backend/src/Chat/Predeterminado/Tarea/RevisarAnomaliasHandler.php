<?php

declare(strict_types=1);

namespace App\Chat\Predeterminado\Tarea;

use App\Chat\Predeterminado\Despacho;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RevisarAnomaliasHandler
{
    public function __construct(
        private readonly Despacho $despacho,
        private readonly ClockInterface $reloj,
    ) {}

    /** @return array{envios: int, destinatarios: int, fallidos: int} */
    public function __invoke(RevisarAnomalias $mensaje): array
    {
        return $this->despacho->anomalias(\DateTimeImmutable::createFromInterface($this->reloj->now())->modify("-1 day"));
    }
}
