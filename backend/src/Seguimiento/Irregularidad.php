<?php

declare(strict_types=1);

namespace App\Seguimiento;

/** Algo que avisar en la trayectoria GPS de un bus (`Irregularidades`). */
final readonly class Irregularidad
{
    public const EXCESO_VELOCIDAD = "exceso_velocidad";
    public const PARADA_LARGA = "parada_larga";

    public function __construct(
        public string $tipo,
        /** Instantes (epoch) de la primera y la última lectura del tramo. */
        public int $desde,
        public int $hasta,
        /** Dónde empezó. */
        public float $lat,
        public float $lng,
        /** La mayor velocidad del tramo (0 en una parada). */
        public int $maximaKmh,
    ) {}

    /** La misma mientras dure: sirve para avisarla una sola vez. */
    public function clave(): string
    {
        return $this->tipo . ":" . $this->desde;
    }

    public function segundos(): int
    {
        return $this->hasta - $this->desde;
    }
}
