<?php

declare(strict_types=1);

namespace App\Migration\Salida;

/** Ruta del legado ya migrada (ADR-030): su id y el orden de cada parada. */
final readonly class RutaLegada
{
    /** @param array<int, int> $ordenes enclave → orden */
    public function __construct(
        public int $id,
        public array $ordenes,
    ) {}

    public function origen(): int
    {
        return (int) array_search(0, $this->ordenes, true);
    }

    public function destino(): int
    {
        return (int) array_search($this->ultima(), $this->ordenes, true);
    }

    /** Última parada (el destino). */
    public function ultima(): int
    {
        return count($this->ordenes) - 1;
    }

    /**
     * Tramo `[sube, baja)` entre dos enclaves; null si alguno no es parada
     * o van hacia atrás.
     *
     * @return array{0: int, 1: int}|null
     */
    public function tramo(int $origen, int $destino): ?array
    {
        $s = $this->ordenes[$origen] ?? null;
        $b = $this->ordenes[$destino] ?? null;

        return $s !== null && $b !== null && $s < $b ? [$s, $b] : null;
    }
}
