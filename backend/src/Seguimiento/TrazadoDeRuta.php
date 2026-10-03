<?php

declare(strict_types=1);

namespace App\Seguimiento;

/**
 * Convierte las estaciones de una ruta del legado (en orden) en los puntos por
 * los que pasa el bus, con coordenadas y kilómetros acumulados.
 *
 * Las coordenadas salen del GPS de la estación si lo tiene, si no del catálogo
 * por nombre o, como último recurso, del centro del departamento. Como el
 * legado no garantiza que la lista de estaciones intermedias sea un recorrido
 * ordenado ni que estén bien ubicadas, se depura para no inventar movimientos:
 *  - una intermedia solo ubicada por departamento se descarta (el centro del
 *    departamento puede quedar a decenas de km y haría retroceder al bus);
 *  - estaciones consecutivas casi en el mismo sitio se funden en una parada;
 *  - una intermedia que obliga a un desvío enorme respecto a sus vecinas se descarta.
 * Origen y destino siempre se conservan; si alguno no se puede ubicar, no hay trazado.
 *
 * Los km acumulados se reparten según la distancia en línea recta pero suman
 * los `kilometros` de la ruta (la carretera es más larga que la recta).
 */
final class TrazadoDeRuta
{
    /** Factor carretera/línea recta si la ruta no tiene kilómetros. */
    private const FACTOR_CARRETERA = 1.3;
    /** Estaciones a menos de esto (km) entre sí son la misma parada. */
    private const MISMO_SITIO_KM = 2.0;
    /** Desvío mínimo (km) para considerar descartar una intermedia. */
    private const DESVIO_MINIMO_KM = 30.0;

    /**
     * @param list<array{id: int, nombre: string, latitude: mixed, longitude: mixed, departamento: ?string}> $estaciones origen → destino
     * @return list<array{nombre: string, lat: float, lng: float, km: float, origen: string}>|null
     */
    public static function trazar(array $estaciones, float $kilometros): ?array
    {
        $estaciones = self::sinRepetidas($estaciones);
        $n = count($estaciones);
        if ($n < 2) {
            return null;
        }

        $puntos = [];
        foreach ($estaciones as $i => $e) {
            [$coord, $origen] = self::resolver($e);
            $extremo = 0 === $i || $n - 1 === $i;
            if (null === $coord && $extremo) {
                return null;
            }
            if (null === $coord || ('departamento' === $origen && !$extremo)) {
                continue;
            }
            $puntos[] = ['nombre' => $e['nombre'], 'lat' => $coord[0], 'lng' => $coord[1], 'origen' => $origen, 'extremo' => $extremo];
        }

        $puntos = self::fundirCercanas($puntos);
        if (count($puntos) < 2) {
            return null;
        }
        $puntos = self::sinDesvios($puntos);

        $tramos = [];
        for ($i = 1; $i < count($puntos); $i++) {
            $tramos[] = self::distancia($puntos[$i - 1], $puntos[$i]);
        }
        $recta = array_sum($tramos);
        $total = $kilometros > 0 ? $kilometros : $recta * self::FACTOR_CARRETERA;
        $escala = $recta > 0 ? $total / $recta : 0.0;

        $km = 0.0;
        $out = [];
        foreach ($puntos as $i => $p) {
            if ($i > 0) {
                $km += $tramos[$i - 1] * $escala;
            }
            $out[] = ['nombre' => $p['nombre'], 'lat' => $p['lat'], 'lng' => $p['lng'], 'km' => $km, 'origen' => $p['origen']];
        }

        return $out;
    }

    /** El legado trae latitud y longitud intercambiadas en algunas estaciones: Guatemala está a ~15°N y ~90°O. */
    public static function normalizarGps(mixed $latitud, mixed $longitud): ?array
    {
        if (!is_numeric($latitud) || !is_numeric($longitud)) {
            return null;
        }
        $a = (float) $latitud;
        $b = (float) $longitud;
        if (0.0 === $a && 0.0 === $b) {
            return null;
        }
        [$lat, $lng] = abs($a) > 60 && abs($b) <= 60 ? [$b, $a] : [$a, $b];

        return abs($lat) <= 90 && abs($lng) <= 180 ? [$lat, $lng] : null;
    }

    /** @return array{0: ?array{0: float, 1: float}, 1: string} */
    private static function resolver(array $e): array
    {
        if ($gps = self::normalizarGps($e['latitude'], $e['longitude'])) {
            return [$gps, 'gps'];
        }
        if ($c = Gazetario::porNombre($e['nombre'])) {
            return [$c, 'catalogo'];
        }
        if ($c = Gazetario::porDepartamento($e['departamento'])) {
            return [$c, 'departamento'];
        }

        return [null, 'ninguno'];
    }

    /**
     * Funde en una parada las consecutivas casi coincidentes. Si la repetida es
     * el destino gana el destino; el origen nunca se pierde.
     *
     * @param list<array<string, mixed>> $p
     * @return list<array<string, mixed>>
     */
    private static function fundirCercanas(array $p): array
    {
        $out = [];
        foreach ($p as $punto) {
            $previo = $out[count($out) - 1] ?? null;
            if (null !== $previo && self::distancia($previo, $punto) < self::MISMO_SITIO_KM) {
                if ($punto['extremo'] && !$previo['extremo']) {
                    $out[count($out) - 1] = $punto;
                }
                continue;
            }
            $out[] = $punto;
        }

        return $out;
    }

    /**
     * Descarta intermedias cuyo desvío (ir por ellas en vez de directo entre sus
     * vecinas) supera el trayecto directo y un mínimo.
     *
     * @param list<array<string, mixed>> $p
     * @return list<array<string, mixed>>
     */
    private static function sinDesvios(array $p): array
    {
        $out = [$p[0]];
        $n = count($p);
        for ($i = 1; $i < $n - 1; $i++) {
            $antes = $out[count($out) - 1];
            $directo = self::distancia($antes, $p[$i + 1]);
            $desvio = self::distancia($antes, $p[$i]) + self::distancia($p[$i], $p[$i + 1]) - $directo;
            if ($desvio <= max(self::DESVIO_MINIMO_KM, $directo)) {
                $out[] = $p[$i];
            }
        }
        $out[] = $p[$n - 1];

        return $out;
    }

    private static function distancia(array $a, array $b): float
    {
        return Geo::distanciaKm($a['lat'], $a['lng'], $b['lat'], $b['lng']);
    }

    /** @param list<array<string, mixed>> $estaciones */
    private static function sinRepetidas(array $estaciones): array
    {
        $out = [];
        foreach ($estaciones as $e) {
            if ([] === $out || $out[count($out) - 1]['id'] !== $e['id']) {
                $out[] = $e;
            }
        }

        return $out;
    }
}
