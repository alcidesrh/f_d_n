<?php

declare(strict_types=1);

namespace App\Seguimiento;

/** Geometría sobre la esfera, sin estado. Coordenadas en grados WGS84. */
final class Geo
{
    private const RADIO_TIERRA_KM = 6371.0088;

    /** Distancia en línea recta (gran círculo) en km. */
    public static function distanciaKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $f1 = deg2rad($lat1);
        $f2 = deg2rad($lat2);
        $df = $f2 - $f1;
        $dl = deg2rad($lng2 - $lng1);
        $a = sin($df / 2) ** 2 + cos($f1) * cos($f2) * sin($dl / 2) ** 2;

        return 2 * self::RADIO_TIERRA_KM * asin(min(1.0, sqrt($a)));
    }

    /** Rumbo inicial de A hacia B en grados [0, 360), 0 = norte. */
    public static function rumbo(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $f1 = deg2rad($lat1);
        $f2 = deg2rad($lat2);
        $dl = deg2rad($lng2 - $lng1);
        $y = sin($dl) * cos($f2);
        $x = cos($f1) * sin($f2) - sin($f1) * cos($f2) * cos($dl);

        return fmod(rad2deg(atan2($y, $x)) + 360.0, 360.0);
    }

    /**
     * Punto a la fracción $t (0..1) entre A y B. Interpolación lineal en
     * lat/lng: a las distancias de un tramo de bus el error frente al gran
     * círculo es de metros.
     *
     * @return array{0: float, 1: float}
     */
    public static function interpolar(float $lat1, float $lng1, float $lat2, float $lng2, float $t): array
    {
        $t = max(0.0, min(1.0, $t));

        return [$lat1 + ($lat2 - $lat1) * $t, $lng1 + ($lng2 - $lng1) * $t];
    }
}
