<?php

declare(strict_types=1);

namespace App\Ruta;

use App\Mapa\Valhalla;

/**
 * Km por carretera entre dos lugares: pregunta al Valhalla propio (ADR-055) por
 * las coordenadas de los enclaves. Si falta una coordenada o Valhalla no responde
 * (no corre, o todavía construye su grafo), devuelve nulo y la ruta conserva los
 * km que escribió la persona.
 */
final class KilometrosCarretera
{
    public function __construct(private readonly Valhalla $valhalla) {}

    /** Km por carretera entre dos puntos, o nulo si no se pudo calcular. */
    public function entre(?float $latA, ?float $lngA, ?float $latB, ?float $lngB): ?float
    {
        if ($latA === null || $lngA === null || $latB === null || $lngB === null) {
            return null;
        }

        return $this->valhalla->recorrido([[$latA, $lngA], [$latB, $lngB]])?->km;
    }

    /**
     * Km acumulados desde el origen, a partir de los tramos entre paradas
     * consecutivas (`tramos[i]` va de la parada i a la i+1). Nulo si falta
     * alguno: una ruta a medias no tiene km confiables.
     *
     * @param list<?float> $tramos
     *
     * @return list<int>|null
     */
    public static function acumular(array $tramos): ?array
    {
        $total = 0.0;
        $acumulados = [0];
        foreach ($tramos as $tramo) {
            if ($tramo === null) {
                return null;
            }
            $total += $tramo;
            $acumulados[] = (int) round($total);
        }

        return $acumulados;
    }
}
