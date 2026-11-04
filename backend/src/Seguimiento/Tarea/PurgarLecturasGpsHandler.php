<?php

declare(strict_types=1);

namespace App\Seguimiento\Tarea;

use App\Seguimiento\HistorialGps;
use App\Seguimiento\TelefonosDePrueba;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PurgarLecturasGpsHandler
{
    public function __construct(
        private readonly HistorialGps $historial,
        private readonly TelefonosDePrueba $telefonos,
        private readonly ClockInterface $reloj,
    ) {}

    /** @return array{borradas: int, deTelefonos: int} */
    public function __invoke(PurgarLecturasGps $mensaje): array
    {
        $antes = \DateTimeImmutable::createFromInterface($this->reloj->now())->modify(PurgarLecturasGps::ANTIGUEDAD);

        return ['borradas' => $this->historial->purgar($antes), 'deTelefonos' => $this->telefonos->purgar($antes)];
    }
}
