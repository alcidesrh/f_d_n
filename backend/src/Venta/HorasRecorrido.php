<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\Recorrido;

/**
 * Hora estimada de un recorrido en cada parada: la salida más la duración
 * estimada del trayecto origen→parada. Es la hora que importa al pasajero
 * que sube a mitad de ruta (la que se imprime en el boleto).
 */
final class HorasRecorrido
{
    public function __construct(
        private readonly Itinerarios $itinerarios,
    ) {}

    /** Hora estimada en la parada; null si no se conoce la duración. */
    public function enParada(Recorrido $recorrido, int $enclaveId): ?\DateTimeImmutable
    {
        $minutos = $this->itinerarios->deTrayecto($recorrido->getTrayecto())->minutosHasta($enclaveId);

        return $minutos === null
            ? null
            : \DateTimeImmutable::createFromMutable($recorrido->getFecha())->modify("+{$minutos} minutes");
    }

    /** Como `enParada`, pero cae en la salida del recorrido si no se conoce. */
    public function salidaDesde(Recorrido $recorrido, int $enclaveId): \DateTimeImmutable
    {
        return $this->enParada($recorrido, $enclaveId)
            ?? \DateTimeImmutable::createFromMutable($recorrido->getFecha());
    }
}
