<?php

declare(strict_types=1);

namespace App\Afectacion\Tarea;

use App\Afectacion\Centinela;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class VigilarAfectacionesHandler
{
    public function __construct(
        private readonly Centinela $centinela,
        private readonly ClockInterface $reloj,
    ) {}

    /** @return array{abiertas: int, iniciales: int, escalamientos: int, sinDestinatarios: int, fallidos: int} */
    public function __invoke(VigilarAfectaciones $mensaje): array
    {
        return $this->centinela->revisar(\DateTimeImmutable::createFromInterface($this->reloj->now()));
    }
}
