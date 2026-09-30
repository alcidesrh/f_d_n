<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\Salida;

/**
 * Hora estimada de un salida en cada parada: la salida más la duración
 * estimada del trayecto origen→parada. Es la hora que importa al pasajero
 * que sube a mitad de ruta (la que se imprime en el boleto).
 */
final class HorasSalida
{
    public function __construct(
        private readonly Itinerarios $itinerarios,
    ) {}

    /** Hora estimada en la parada; null si no se conoce la duración. */
    public function enParada(Salida $salida, int $enclaveId): ?\DateTimeImmutable
    {
        $minutos = $this->itinerarios->deTrayecto($salida->getTrayecto())->minutosHasta($enclaveId);

        return $minutos === null
            ? null
            : \DateTimeImmutable::createFromMutable($salida->getFecha())->modify("+{$minutos} minutes");
    }

    /** Como `enParada`, pero cae en la salida del salida si no se conoce. */
    public function salidaDesde(Salida $salida, int $enclaveId): \DateTimeImmutable
    {
        return $this->enParada($salida, $enclaveId)
            ?? \DateTimeImmutable::createFromMutable($salida->getFecha());
    }
}
