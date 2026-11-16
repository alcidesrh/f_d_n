<?php

declare(strict_types=1);

namespace App\Mantenimiento;

use App\Seguimiento\Geo;

/**
 * Kilómetros que hizo un bus según una serie de lecturas GPS (ADR-065). Puro.
 *
 * Suma tramos en línea recta entre lecturas, con dos cuidados: el bus
 * detenido no suma el temblor del GPS (un tramo cuenta cuando se aleja
 * `MINIMO_KM` del último punto contado) y una lectura a la que no pudo llegar
 * (más de `MAXIMO_KMH` desde ese punto) se descarta. Entre lecturas muy
 * separadas mide la cuerda, no el camino: se queda corto, nunca largo.
 */
final class Kilometraje
{
    public const MINIMO_KM = 0.05;
    public const MAXIMO_KMH = 150.0;

    /**
     * @param iterable<array{0: int, 1: float, 2: float}> $lecturas `[instante (epoch), lat, lng]`, en orden
     *
     * @return array{km: float, lecturas: int, ultima: ?int} `ultima`: instante de la última lectura vista
     */
    public static function de(iterable $lecturas): array
    {
        $km = 0.0;
        $n = 0;
        $ultima = null;
        $ancla = null;
        foreach ($lecturas as [$t, $lat, $lng]) {
            $n++;
            $ultima = $t;
            if ($ancla === null) {
                $ancla = [$t, $lat, $lng];
                continue;
            }
            $d = Geo::distanciaKm($ancla[1], $ancla[2], $lat, $lng);
            if ($d < self::MINIMO_KM) {
                // Sigue en el mismo sitio: desde este instante se mide la velocidad del próximo tramo.
                $ancla[0] = $t;
                continue;
            }
            $horas = ($t - $ancla[0]) / 3600;
            if ($horas <= 0 || $d / $horas > self::MAXIMO_KMH) {
                continue;
            }
            $km += $d;
            $ancla = [$t, $lat, $lng];
        }

        return ["km" => $km, "lecturas" => $n, "ultima" => $ultima];
    }
}
