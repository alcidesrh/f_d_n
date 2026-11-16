<?php

declare(strict_types=1);

namespace App\Seguimiento;

/**
 * Hora estimada de llegada de una salida a cada parada (ADR-064), pura: el
 * atraso del bus es la diferencia entre la hora de su última lectura GPS y
 * la hora a la que el horario lo ponía en ese punto de la ruta; ese atraso
 * se suma al horario de las paradas que le faltan. Sin lectura, las horas
 * son las del horario.
 *
 * El punto de la ruta es la proyección de la lectura sobre la línea que une
 * las paradas con coordenadas (recta por tramo, como `PlanDeViaje`); la hora
 * del horario ahí, la interpolación entre las dos paradas del tramo.
 */
final class HoraEstimada
{
    /** Una lectura a más de esto de la ruta no dice dónde va el bus: no se estima con ella. */
    public const FUERA_DE_RUTA_KM = 15.0;
    /** Un bus que todavía no sale de su origen no lleva adelanto. */
    private const EN_EL_ORIGEN_KM = 1.0;

    /**
     * @param list<array{enclave: int, nombre: string, lat: ?float, lng: ?float, hora: ?int}> $horario paradas en orden; `hora` en epoch, null si no se conoce
     * @param array{lat: float, lng: float, instante: int}|null $lectura última lectura GPS vigente
     *
     * @return array{fuente: 'gps'|'horario', atrasoMinutos: ?int, paradas: list<array{enclave: int, nombre: string, programada: ?int, estimada: ?int, paso: bool}>}
     */
    public static function calcular(array $horario, ?array $lectura, int $ahora): array
    {
        $punto = $lectura === null ? null : self::ubicar($horario, $lectura["lat"], $lectura["lng"]);
        if ($lectura === null || $punto === null) {
            return [
                "fuente" => "horario",
                "atrasoMinutos" => null,
                "paradas" => array_map(static fn(array $p) => self::parada($p, $p["hora"], false), $horario),
            ];
        }

        $atraso = $lectura["instante"] - $punto["hora"];
        if ($punto["enOrigen"]) {
            $atraso = max(0, $atraso);
        }

        $paradas = [];
        foreach ($horario as $i => $p) {
            $paso = $i <= $punto["indice"] && !($i === 0 && $punto["enOrigen"]);
            // Lo que falta no puede llegar antes de ahora; por lo que ya pasó no se estima.
            $estimada = $paso || $p["hora"] === null ? null : max($ahora, $p["hora"] + $atraso);
            $paradas[] = self::parada($p, $estimada, $paso);
        }

        return ["fuente" => "gps", "atrasoMinutos" => (int) round($atraso / 60), "paradas" => $paradas];
    }

    /**
     * Dónde va el bus en el horario: la última parada que dejó atrás, la hora
     * del horario en ese punto y si sigue en el origen. Null si no hay dos
     * paradas con coordenadas y hora, o la lectura cae lejos de la ruta.
     *
     * @param list<array{enclave: int, nombre: string, lat: ?float, lng: ?float, hora: ?int}> $horario
     *
     * @return array{indice: int, hora: int, enOrigen: bool}|null
     */
    public static function ubicar(array $horario, float $lat, float $lng): ?array
    {
        $ubicadas = array_filter($horario, static fn(array $p) => $p["lat"] !== null && $p["lng"] !== null && $p["hora"] !== null);
        if (count($ubicadas) < 2) {
            return null;
        }
        $indices = array_keys($ubicadas);
        $coseno = cos(deg2rad($lat));
        $mejor = null;
        $menor = INF;
        for ($k = 0; $k < count($indices) - 1; $k++) {
            $a = $horario[$indices[$k]];
            $b = $horario[$indices[$k + 1]];
            $dx = ($b["lng"] - $a["lng"]) * $coseno;
            $dy = $b["lat"] - $a["lat"];
            $largo = $dx * $dx + $dy * $dy;
            $f = $largo > 0 ? max(0.0, min(1.0, ((($lng - $a["lng"]) * $coseno) * $dx + ($lat - $a["lat"]) * $dy) / $largo)) : 0.0;
            [$pLat, $pLng] = Geo::interpolar($a["lat"], $a["lng"], $b["lat"], $b["lng"], $f);
            $distancia = Geo::distanciaKm($lat, $lng, $pLat, $pLng);
            if ($distancia < $menor) {
                $menor = $distancia;
                $enOrigen = $k === 0 && Geo::distanciaKm($lat, $lng, $a["lat"], $a["lng"]) <= self::EN_EL_ORIGEN_KM;
                $mejor = [
                    // Al llegar a la parada del final del tramo, ya la pasó.
                    "indice" => $f >= 1.0 ? $indices[$k + 1] : $indices[$k],
                    // En el origen cuenta su hora de salida, no los metros que se haya movido en la estación.
                    "hora" => $enOrigen ? $a["hora"] : (int) round($a["hora"] + ($b["hora"] - $a["hora"]) * $f),
                    "enOrigen" => $enOrigen,
                ];
            }
        }

        return $menor <= self::FUERA_DE_RUTA_KM ? $mejor : null;
    }

    /**
     * @param array{enclave: int, nombre: string, hora: ?int} $p
     *
     * @return array{enclave: int, nombre: string, programada: ?int, estimada: ?int, paso: bool}
     */
    private static function parada(array $p, ?int $estimada, bool $paso): array
    {
        return ["enclave" => $p["enclave"], "nombre" => $p["nombre"], "programada" => $p["hora"], "estimada" => $estimada, "paso" => $paso];
    }
}
