<?php

declare(strict_types=1);

namespace App\Mapa;

/** Un recorrido por carretera: largo, duración estimada y trazado (ADR-055). */
final readonly class Recorrido
{
    /**
     * @param list<array{float, float}> $trazado puntos [latitud, longitud] de la carretera, en orden
     */
    public function __construct(
        public float $km,
        public float $segundos,
        public array $trazado,
    ) {}
}
