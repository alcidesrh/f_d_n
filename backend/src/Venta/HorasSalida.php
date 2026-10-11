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

    /**
     * Hora de subida en una parada con su precisión. Si no se conoce, la hora
     * en el origen del bus marcada `desconocida`: quien la muestra no debe
     * presentarla como la hora del pasajero.
     *
     * @return array{hora: \DateTimeImmutable, precision: PrecisionHora}
     */
    public function subida(Salida $salida, int $enclaveId): array
    {
        return $this->itinerarios->deSalida($salida)->hora($enclaveId)
            ?? ["hora" => \DateTimeImmutable::createFromMutable($salida->getFecha()), "precision" => PrecisionHora::DESCONOCIDA];
    }
}
