<?php

declare(strict_types=1);

namespace App\Seguimiento;

/**
 * Una trama del protocolo OsmAnd, el que hablan Traccar Client (Android/iOS)
 * y muchos rastreadores. Llega de dos formas:
 *
 * - parámetros (query o formulario): `id`, `lat`, `lon`, `timestamp` (epoch
 *   en s o ms, o ISO 8601), `speed` (nudos), `bearing`;
 * - JSON (Traccar Client 9+): `{device_id, location: {timestamp, coords:
 *   {latitude, longitude, speed (m/s), heading}}}`.
 *
 * El identificador del dispositivo es el código del bus.
 */
final readonly class LecturaOsmAnd
{
    private const NUDO_KMH = 1.852;
    private const MS_KMH = 3.6;
    /** Por debajo de esto el bus se da por detenido (ruido del GPS parado). */
    private const DETENIDO_KMH = 3.0;

    private function __construct(
        public string $busCodigo,
        public Posicion $posicion,
    ) {}

    /**
     * @param array<string, mixed> $parametros query + formulario
     * @param array<string, mixed>|null $json cuerpo JSON, si vino
     *
     * @throws \InvalidArgumentException si falta el dispositivo o la coordenada no sirve
     */
    public static function desde(array $parametros, ?array $json, int $ahora): self
    {
        if (is_array($json) && is_array($json['location'] ?? null)) {
            $loc = $json['location'];
            $coords = is_array($loc['coords'] ?? null) ? $loc['coords'] : [];
            $id = $json['device_id'] ?? $parametros['id'] ?? null;
            $lat = $coords['latitude'] ?? null;
            $lng = $coords['longitude'] ?? null;
            $velocidad = is_numeric($coords['speed'] ?? null) ? max(0.0, (float) $coords['speed']) * self::MS_KMH : 0.0;
            $rumbo = $coords['heading'] ?? 0;
            $instante = $loc['timestamp'] ?? null;
        } else {
            $id = $parametros['id'] ?? $parametros['deviceid'] ?? null;
            $lat = $parametros['lat'] ?? null;
            $lng = $parametros['lon'] ?? null;
            $velocidad = is_numeric($parametros['speed'] ?? null) ? max(0.0, (float) $parametros['speed']) * self::NUDO_KMH : 0.0;
            $rumbo = $parametros['bearing'] ?? $parametros['heading'] ?? 0;
            $instante = $parametros['timestamp'] ?? null;
        }

        $id = is_scalar($id) ? trim((string) $id) : '';
        if ('' === $id || strlen($id) > 64) {
            throw new \InvalidArgumentException('Falta el identificador del dispositivo (código del bus).');
        }
        if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            throw new \InvalidArgumentException('Coordenada inválida.');
        }
        $rumbo = is_numeric($rumbo) && (float) $rumbo >= 0 ? fmod((float) $rumbo, 360.0) : 0.0;

        return new self($id, new Posicion(
            (float) $lat,
            (float) $lng,
            $rumbo,
            $velocidad,
            // Los km los pone `SeguimientoBuses` proyectando sobre la ruta de la salida.
            0.0,
            $velocidad < self::DETENIDO_KMH ? Posicion::DETENIDO : Posicion::EN_RUTA,
            self::instante($instante, $ahora),
            'gps',
        ));
    }

    /** Epoch en segundos. Sin hora o con una del futuro (reloj del teléfono adelantado), la de llegada. */
    private static function instante(mixed $valor, int $ahora): int
    {
        if (is_numeric($valor)) {
            $t = (int) $valor;
            $t = $t > 100_000_000_000 ? intdiv($t, 1000) : $t;
        } elseif (is_string($valor) && false !== ($fecha = date_create_immutable($valor))) {
            $t = $fecha->getTimestamp();
        } else {
            return $ahora;
        }

        return $t > 0 ? min($t, $ahora) : $ahora;
    }
}
