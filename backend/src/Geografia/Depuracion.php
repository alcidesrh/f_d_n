<?php

declare(strict_types=1);

namespace App\Geografia;

/**
 * Limpieza del dataset de la página anterior (ADR-041), pura: los nombres
 * vienen en inglés con sufijo ("Petén Department", "Cayo District") y en
 * Guatemala hay municipios repetidos ("Flores" y "Municipio de Flores").
 */
final class Depuracion
{
    private const SUFIJOS = '/\s+(Department|District|Province)$/u';
    private const PREFIJOS = '/^(Provincia|Departamento|Municipio) de\s+/u';

    /**
     * Nombres de las regiones de un país sin sufijo ni prefijo; si al
     * quitarlo dos quedan iguales, esas conservan el original.
     *
     * @param array<int, string> $nombres por id
     *
     * @return array<int, string>
     */
    public static function regiones(array $nombres): array
    {
        $limpios = array_map(static fn(string $n) => self::sinAfijos($n), $nombres);
        $usos = array_count_values(array_map(self::clave(...), $limpios));

        $resultado = [];
        foreach ($nombres as $id => $original) {
            $resultado[$id] = $usos[self::clave($limpios[$id])] > 1 ? self::espacios($original) : $limpios[$id];
        }

        return $resultado;
    }

    /**
     * Ciudades de una región sin repetidas: "Municipio de X" se descarta si
     * ya está "X" y, si no, pasa a llamarse "X". El orden no se conserva
     * (las consultas ordenan por nombre).
     *
     * @param list<string> $nombres
     *
     * @return list<string>
     */
    public static function ciudades(array $nombres): array
    {
        $vistas = [];
        $resultado = [];
        // Primero los nombres sin prefijo, para que "X" gane a "Municipio de X".
        $ordenados = $nombres;
        usort($ordenados, static fn(string $a, string $b) => (int) self::conPrefijo($a) <=> (int) self::conPrefijo($b));
        foreach ($ordenados as $nombre) {
            $limpio = self::sinAfijos($nombre);
            if ($limpio === "" || isset($vistas[self::clave($limpio)])) {
                continue;
            }
            $vistas[self::clave($limpio)] = true;
            $resultado[] = $limpio;
        }

        return $resultado;
    }

    private static function sinAfijos(string $nombre): string
    {
        $limpio = self::espacios($nombre);
        $sinAfijos = trim((string) preg_replace([self::SUFIJOS, self::PREFIJOS], "", $limpio));

        return $sinAfijos !== "" ? $sinAfijos : $limpio;
    }

    private static function conPrefijo(string $nombre): bool
    {
        return preg_match(self::PREFIJOS, self::espacios($nombre)) === 1;
    }

    private static function espacios(string $nombre): string
    {
        return trim((string) preg_replace('/\s+/u', " ", $nombre));
    }

    private static function clave(string $nombre): string
    {
        return mb_strtolower($nombre);
    }
}
