<?php

declare(strict_types=1);

namespace App\Seguimiento;

/**
 * Lo irregular en las lecturas GPS de un bus (`HistorialGps::lecturas`), sin
 * tocar la base: exceso de velocidad sostenido y paradas largas fuera de las
 * paradas de su ruta. Un tramo se da en cuanto cumple el umbral, aunque siga:
 * su `clave()` no cambia mientras dura, así quien avisa lo hace una vez.
 *
 * Un hueco sin señal corta el tramo: no se supone qué hizo el bus sin lecturas.
 */
final class Irregularidades
{
    /**
     * @param list<array{int, float, float, int, int}> $lecturas `[instante, lat, lng, velocidadKmh, rumbo]`, en orden
     * @param list<array{float, float}>                 $paradas  `[lat, lng]` de las paradas de la ruta
     *
     * @return list<Irregularidad> en el orden en que empezaron
     */
    public static function detectar(array $lecturas, array $paradas, UmbralesIrregularidad $u = new UmbralesIrregularidad()): array
    {
        $halladas = [];
        // Tramo abierto de cada tipo: [desde, hasta, lat, lng, máxima].
        $exceso = null;
        $parada = null;
        $anterior = null;
        $cerrar = static function (?array $tramo, string $tipo, int $minimo) use (&$halladas): void {
            if ($tramo !== null && $tramo[1] - $tramo[0] >= $minimo) {
                $halladas[] = new Irregularidad($tipo, $tramo[0], $tramo[1], $tramo[2], $tramo[3], $tramo[4]);
            }
        };

        foreach ($lecturas as [$instante, $lat, $lng, $velocidad]) {
            if ($anterior !== null && $instante - $anterior > $u->segundosSinSenal) {
                $cerrar($exceso, Irregularidad::EXCESO_VELOCIDAD, $u->segundosExceso);
                $cerrar($parada, Irregularidad::PARADA_LARGA, $u->segundosDetenido);
                $exceso = $parada = null;
            }
            $anterior = $instante;

            if ($velocidad > $u->velocidadMaximaKmh) {
                $exceso = $exceso === null ? [$instante, $instante, $lat, $lng, $velocidad] : [$exceso[0], $instante, $exceso[2], $exceso[3], max($exceso[4], $velocidad)];
            } else {
                $cerrar($exceso, Irregularidad::EXCESO_VELOCIDAD, $u->segundosExceso);
                $exceso = null;
            }

            if ($velocidad < HistorialGps::DETENIDO_KMH && !self::enParada($lat, $lng, $paradas, $u->radioParadaKm)) {
                $parada = $parada === null ? [$instante, $instante, $lat, $lng, 0] : [$parada[0], $instante, $parada[2], $parada[3], 0];
            } else {
                $cerrar($parada, Irregularidad::PARADA_LARGA, $u->segundosDetenido);
                $parada = null;
            }
        }
        $cerrar($exceso, Irregularidad::EXCESO_VELOCIDAD, $u->segundosExceso);
        $cerrar($parada, Irregularidad::PARADA_LARGA, $u->segundosDetenido);
        usort($halladas, static fn (Irregularidad $a, Irregularidad $b): int => [$a->desde, $a->tipo] <=> [$b->desde, $b->tipo]);

        return $halladas;
    }

    /** @param list<array{float, float}> $paradas */
    private static function enParada(float $lat, float $lng, array $paradas, float $radioKm): bool
    {
        foreach ($paradas as [$pLat, $pLng]) {
            if (Geo::distanciaKm($lat, $lng, $pLat, $pLng) <= $radioKm) {
                return true;
            }
        }

        return false;
    }
}
