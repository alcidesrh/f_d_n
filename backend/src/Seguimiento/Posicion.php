<?php

declare(strict_types=1);

namespace App\Seguimiento;

/** Dónde está un bus en un instante. `fuente` dice de dónde sale el dato. */
final readonly class Posicion
{
    public const POR_SALIR = 'por_salir';
    public const EN_RUTA = 'en_ruta';
    public const DETENIDO = 'detenido';
    public const LLEGO = 'llego';

    public function __construct(
        public float $lat,
        public float $lng,
        /** Grados, 0 = norte. */
        public float $rumbo,
        public float $velocidadKmh,
        public float $km,
        public string $estado,
        /** Instante al que corresponde (epoch en segundos). */
        public int $instante,
        /** `simulada` o `gps`. */
        public string $fuente = 'simulada',
    ) {}
}
