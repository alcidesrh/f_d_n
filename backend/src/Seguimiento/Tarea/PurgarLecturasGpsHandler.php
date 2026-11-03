<?php

declare(strict_types=1);

namespace App\Seguimiento\Tarea;

use App\Seguimiento\HistorialGps;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PurgarLecturasGpsHandler
{
    public function __construct(
        private readonly HistorialGps $historial,
        private readonly ClockInterface $reloj,
    ) {}

    /** @return array{borradas: int} */
    public function __invoke(PurgarLecturasGps $mensaje): array
    {
        return ['borradas' => $this->historial->purgar(\DateTimeImmutable::createFromInterface($this->reloj->now())->modify(PurgarLecturasGps::ANTIGUEDAD))];
    }
}
