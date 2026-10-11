<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\Enum\PrecisionHora;
use App\Entity\Salida;

/** Hora de una salida en sus paradas, desde su horario (`SalidaParada`, ADR-030). */
final class HorasSalida
{
    public function __construct(
        private readonly Itinerarios $itinerarios,
    ) {}

    /** Hora estimada en la parada; null si no se conoce o no es parada. */
    public function enParada(Salida $salida, int $enclaveId): ?\DateTimeImmutable
    {
        $h = $this->itinerarios->deSalida($salida)->hora($enclaveId);

        return $h === null || $h["precision"] === PrecisionHora::DESCONOCIDA ? null : $h["hora"];
    }

    /** Como `enParada`, pero cae en la salida del salida si no se conoce. */
    public function salidaDesde(Salida $salida, int $enclaveId): \DateTimeImmutable
    {
        return $this->enParada($salida, $enclaveId)
            ?? \DateTimeImmutable::createFromMutable($salida->getFecha());
    }
}
