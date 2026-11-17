<?php

declare(strict_types=1);

namespace App\Venta\Descuento;

/**
 * El descuento que le toca a un asiento (ADR-066): el porcentaje que gana,
 * por qué, y el tipo de pasajero y la promoción que se consideraron.
 */
final readonly class Descuento
{
    public function __construct(
        public int $porciento,
        /** «Niño 50 %», «Diciembre 20 %». */
        public string $motivo,
        public ?int $tipoPasajeroId = null,
        public ?int $promocionId = null,
    ) {}
}
