<?php

declare(strict_types=1);

namespace App\Venta;

/**
 * Porción de un itinerario entre dos paradas, por posición: `[desde, hasta)`.
 * Un asiento vendido para un tramo queda libre para cualquier otro tramo que
 * no se solape (p. ej. A→B y B→C comparten asiento).
 */
final readonly class Tramo
{
    public function __construct(
        public int $desde,
        public int $hasta,
    ) {
        if ($desde >= $hasta) {
            throw new \InvalidArgumentException(
                "Un tramo va hacia adelante: desde ({$desde}) < hasta ({$hasta}).",
            );
        }
    }

    public function seSolapaCon(self $otro): bool
    {
        return $this->desde < $otro->hasta && $otro->desde < $this->hasta;
    }
}
