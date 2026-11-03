<?php

declare(strict_types=1);

namespace App\Seguimiento;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Historial de lecturas GPS de cada bus (`lectura_gps`, ADR-050): lo escribe
 * cada trama que llega a `/api/seguimiento/gps` y lo leen la reproducción de
 * trayectorias y la purga.
 *
 * Un bus quieto no llena la tabla: detenido en el mismo sitio se guarda a lo
 * sumo una lectura cada `QUIETO_SEGUNDOS` (el teléfono puede quedar encendido
 * toda la noche en el predio).
 */
class HistorialGps
{
    /** Con el bus detenido en el mismo sitio, una lectura cada tanto. */
    public const QUIETO_SEGUNDOS = 120;
    /** Por debajo de esto el bus está detenido (como `LecturaOsmAnd`). */
    private const DETENIDO_KMH = 3;
    /** "El mismo sitio": a menos de esto, en grados (~55 m), de la última guardada. */
    private const MISMO_SITIO_GRADOS = 0.0005;
    /** Tope de lecturas por trayectoria (una cada 3 s durante 40 h). */
    private const MAXIMO_LECTURAS = 50_000;

    public function __construct(private readonly Connection $db) {}

    /** Guarda la lectura si el código es el de un bus; la misma (bus e instante) no se repite. */
    public function registrar(string $busCodigo, Posicion $p): bool
    {
        $velocidad = (int) round(min(max($p->velocidadKmh, 0.0), 300.0));
        $quieto = $velocidad < self::DETENIDO_KMH
            ? <<<'SQL'
                AND NOT EXISTS (
                    SELECT 1 FROM lectura_gps u
                    WHERE u.bus_id = b.id
                      AND u.instante > to_timestamp(CAST(:desde AS BIGINT)) AND u.instante <= to_timestamp(CAST(:instante AS BIGINT))
                      AND u.velocidad_kmh < CAST(:detenido AS SMALLINT)
                      AND abs(u.lat - CAST(:lat AS DOUBLE PRECISION)) < CAST(:sitio AS DOUBLE PRECISION)
                      AND abs(u.lng - CAST(:lng AS DOUBLE PRECISION)) < CAST(:sitio AS DOUBLE PRECISION))
                SQL
            : '';

        $parametros = [
            'codigo' => mb_strtoupper(trim($busCodigo)),
            'instante' => $p->instante,
            'lat' => (string) $p->lat,
            'lng' => (string) $p->lng,
            'rumbo' => ((int) round($p->rumbo)) % 360,
            'velocidad' => $velocidad,
        ];
        if ('' !== $quieto) {
            $parametros += ['desde' => $p->instante - self::QUIETO_SEGUNDOS, 'detenido' => self::DETENIDO_KMH, 'sitio' => (string) self::MISMO_SITIO_GRADOS];
        }

        return $this->db->executeStatement(<<<SQL
            INSERT INTO lectura_gps (bus_id, instante, lat, lng, rumbo, velocidad_kmh)
            SELECT b.id, to_timestamp(CAST(:instante AS BIGINT)), CAST(:lat AS DOUBLE PRECISION), CAST(:lng AS DOUBLE PRECISION),
                   CAST(:rumbo AS SMALLINT), CAST(:velocidad AS SMALLINT)
            FROM bus b
            WHERE upper(trim(b.codigo)) = :codigo
            $quieto
            ON CONFLICT (bus_id, instante) DO NOTHING
            SQL, $parametros) > 0;
    }

    /**
     * Lecturas de un bus entre dos instantes (epoch en s), en orden.
     *
     * @return list<array{int, float, float, int, int}> `[instante, lat, lng, velocidadKmh, rumbo]`
     */
    public function lecturas(int $busId, int $desde, int $hasta): array
    {
        $filas = $this->db->fetchAllNumeric(<<<'SQL'
            SELECT CAST(EXTRACT(EPOCH FROM instante) AS BIGINT), lat, lng, velocidad_kmh, rumbo
            FROM lectura_gps
            WHERE bus_id = :bus AND instante BETWEEN to_timestamp(:desde) AND to_timestamp(:hasta)
            ORDER BY instante
            LIMIT :tope
            SQL, ['bus' => $busId, 'desde' => $desde, 'hasta' => $hasta, 'tope' => self::MAXIMO_LECTURAS], ['bus' => ParameterType::INTEGER, 'desde' => ParameterType::INTEGER, 'hasta' => ParameterType::INTEGER, 'tope' => ParameterType::INTEGER]);

        return array_map(static fn(array $f) => [(int) $f[0], (float) $f[1], (float) $f[2], (int) $f[3], (int) $f[4]], $filas);
    }

    /**
     * Cuántas lecturas tiene cada salida en su ventana, en una consulta.
     *
     * @param list<array{salida: int, bus: int, desde: int, hasta: int}> $ventanas instantes en epoch (s)
     * @return array<int, array{lecturas: int, primera: int, ultima: int}> por id de salida; solo las que tienen
     */
    public function resumen(array $ventanas): array
    {
        if ([] === $ventanas) {
            return [];
        }
        $lista = static fn(string $campo) => '{' . implode(',', array_map(static fn(array $v) => (int) $v[$campo], $ventanas)) . '}';

        $filas = $this->db->fetchAllAssociative(<<<'SQL'
            SELECT v.salida, COUNT(*) AS lecturas,
                   CAST(EXTRACT(EPOCH FROM MIN(l.instante)) AS BIGINT) AS primera,
                   CAST(EXTRACT(EPOCH FROM MAX(l.instante)) AS BIGINT) AS ultima
            FROM unnest(CAST(:salidas AS INTEGER ARRAY), CAST(:buses AS INTEGER ARRAY), CAST(:desdes AS BIGINT ARRAY), CAST(:hastas AS BIGINT ARRAY)) AS v(salida, bus, desde, hasta)
            JOIN lectura_gps l ON l.bus_id = v.bus AND l.instante BETWEEN to_timestamp(v.desde) AND to_timestamp(v.hasta)
            GROUP BY v.salida
            SQL, ['salidas' => $lista('salida'), 'buses' => $lista('bus'), 'desdes' => $lista('desde'), 'hastas' => $lista('hasta')]);

        $out = [];
        foreach ($filas as $f) {
            $out[(int) $f['salida']] = ['lecturas' => (int) $f['lecturas'], 'primera' => (int) $f['primera'], 'ultima' => (int) $f['ultima']];
        }

        return $out;
    }

    /** Borra las lecturas de antes de `$antesDe`, bus por bus (cada borrado usa la clave). Devuelve cuántas. */
    public function purgar(\DateTimeImmutable $antesDe): int
    {
        $borradas = 0;
        foreach ($this->db->fetchFirstColumn('SELECT id FROM bus ORDER BY id') as $bus) {
            $borradas += (int) $this->db->executeStatement(
                'DELETE FROM lectura_gps WHERE bus_id = :bus AND instante < to_timestamp(:antes)',
                ['bus' => (int) $bus, 'antes' => $antesDe->getTimestamp()],
                ['bus' => ParameterType::INTEGER, 'antes' => ParameterType::INTEGER],
            );
        }

        return $borradas;
    }
}
