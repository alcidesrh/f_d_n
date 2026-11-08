<?php

declare(strict_types=1);

namespace App\Mapa;

/**
 * Polilínea codificada (el formato de Google): así devuelve Valhalla el trazado
 * de cada tramo (`shape`), con seis decimales de precisión.
 */
final class Polilinea
{
    /** @return list<array{float, float}> puntos [latitud, longitud] */
    public static function decodificar(string $codigo, int $precision = 6): array
    {
        $factor = 10 ** $precision;
        $puntos = [];
        $lat = 0;
        $lng = 0;
        $i = 0;
        $largo = strlen($codigo);
        while ($i < $largo) {
            foreach ([0, 1] as $eje) {
                $resultado = 0;
                $corrimiento = 0;
                do {
                    if ($i >= $largo) {
                        throw new \InvalidArgumentException("Polilínea incompleta");
                    }
                    $byte = ord($codigo[$i++]) - 63;
                    $resultado |= ($byte & 0x1F) << $corrimiento;
                    $corrimiento += 5;
                } while ($byte >= 0x20);
                $delta = ($resultado & 1) !== 0 ? ~($resultado >> 1) : $resultado >> 1;
                if ($eje === 0) {
                    $lat += $delta;
                } else {
                    $lng += $delta;
                }
            }
            $puntos[] = [$lat / $factor, $lng / $factor];
        }

        return $puntos;
    }
}
