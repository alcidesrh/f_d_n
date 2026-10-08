<?php

declare(strict_types=1);

namespace App\Migration\Salida;

/** Trayecto de una ruta del legado ya migrado, con sus pares vendibles. */
final readonly class RutaMigrada
{
    /**
     * @param array<string, int> $pares "origen:destino" → id de trayecto (incluye el de la ruta)
     */
    public function __construct(
        public int $trayectoId,
        public int $origenId,
        public int $destinoId,
        public array $pares,
    ) {}

    /** Trayecto de la ruta entre dos de sus paradas (en ese sentido), si existe. */
    public function entre(int $origen, int $destino): ?int
    {
        return $this->pares["{$origen}:{$destino}"] ?? null;
    }
}
