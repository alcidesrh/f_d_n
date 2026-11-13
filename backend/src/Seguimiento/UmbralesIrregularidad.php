<?php

declare(strict_types=1);

namespace App\Seguimiento;

/** Cuándo una trayectoria GPS tiene algo que avisar (`Irregularidades`). */
final readonly class UmbralesIrregularidad
{
    public function __construct(
        /** Por encima de esto el bus va con exceso de velocidad. */
        public int $velocidadMaximaKmh = 90,
        /** El exceso se avisa si se sostiene este tiempo (un pico suelto es ruido del GPS o un rebase). */
        public int $segundosExceso = 180,
        /** Detenido este tiempo fuera de una parada de su ruta es una parada larga. */
        public int $segundosDetenido = 1200,
        /** A menos de esto de una parada de la ruta, estar detenido es lo normal. */
        public float $radioParadaKm = 0.5,
        /** Más que esto entre dos lecturas es un hueco sin señal: corta lo que venía. */
        public int $segundosSinSenal = 300,
    ) {}
}
