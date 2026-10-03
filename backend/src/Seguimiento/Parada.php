<?php

declare(strict_types=1);

namespace App\Seguimiento;

/** Estación del recorrido de una salida, con su horario estimado (epoch en segundos). */
final readonly class Parada
{
    public function __construct(
        public string $nombre,
        public float $lat,
        public float $lng,
        /** Kilómetros desde el origen a lo largo de la ruta. */
        public float $km,
        public int $llegada,
        public int $salida,
        /** `gps` (coordenada de la estación), `catalogo` o `departamento`. */
        public string $origenCoordenada = 'gps',
    ) {}
}
