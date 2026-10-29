<?php

declare(strict_types=1);

namespace App\Programador;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/** Pone una tarea del catálogo en la cola `tareas` fuera de su horario (ADR-044). */
final class EjecucionManual
{
    public function __construct(private readonly MessageBusInterface $bus) {}

    public function encolar(Tarea $tarea): void
    {
        $this->bus->dispatch($tarea->mensaje(), [new TransportNamesStamp([Tarea::COLA_TAREAS])]);
    }
}
