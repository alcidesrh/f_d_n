<?php

declare(strict_types=1);

namespace App\Seguimiento;

/** Supuestos de la simulación, a falta de GPS real. */
final readonly class ParametrosSimulacion
{
    public function __construct(
        /** Velocidad media en marcha (no incluye paradas). */
        public float $velocidadKmh = 68.0,
        /** Espera en cada estación intermedia. */
        public int $paradaMinutos = 4,
        /** Descanso extra en la estación intermedia más cercana a la mitad. */
        public int $descansoMinutos = 20,
        /** Solo hay descanso en rutas de al menos estos km. */
        public float $descansoDesdeKm = 300.0,
        /** Cada bus corre entre 1-x y 1+x de la velocidad media (estable por salida). */
        public float $variacionVelocidad = 0.08,
    ) {}
}
