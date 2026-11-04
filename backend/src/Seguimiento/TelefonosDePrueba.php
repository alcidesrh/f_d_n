<?php

declare(strict_types=1);

namespace App\Seguimiento;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Teléfonos de prueba (temporal, ADR-050): un Traccar Client con un nombre
 * acordado (env `SEGUIMIENTO_GPS_PRUEBAS`, separados por coma; no debe ser el
 * código de un bus) en vez del código de un bus. Se sigue como un bus —mapa en
 * vivo, historial y reproducción— pero sin salida: sus trayectorias las marcan
 * los huecos y las paradas. Más de 5 min entre dos lecturas cierra una
 * trayectoria en la primera y empieza otra en la segunda; más de 5 min
 * detenido la cierra al detenerse y empieza otra al arrancar.
 *
 * Lo ve solo quien ve las salidas de todas las empresas (no es de ninguna).
 */
final class TelefonosDePrueba
{
    /** Más que esto entre dos lecturas separa dos trayectorias (por encima de `HistorialGps::QUIETO_SEGUNDOS`: el raleo del quieto no corta). */
    public const HUECO_SEGUNDOS = 300;
    /** Detenido (`HistorialGps::DETENIDO_KMH`) más que esto, hasta la lectura que arranca, también separa dos trayectorias. */
    public const PARADA_SEGUNDOS = 300;
    /** Tope de una trayectoria: lo que se lee después de su primera lectura. */
    private const MAXIMO_HORAS = 48;
    /** En el mapa en vivo, «Reproducir» ofrece la última trayectoria de estas horas. */
    private const EN_VIVO_HORAS = 12;

    /** @var list<string> */
    private readonly array $nombres;

    public function __construct(
        private readonly Connection $db,
        private readonly FuenteGps $gps,
        #[Autowire(env: 'SEGUIMIENTO_GPS_PRUEBAS')] string $nombres,
    ) {
        $this->nombres = array_values(array_unique(array_filter(array_map(self::normalizar(...), explode(',', $nombres)), static fn(string $n) => '' !== $n)));
    }

    /** El identificador como se guarda: sin espacios en las puntas y en mayúsculas (como el código del bus). */
    public static function normalizar(string $dispositivo): string
    {
        return mb_strtoupper(trim($dispositivo));
    }

    public function es(string $dispositivo): bool
    {
        return in_array(self::normalizar($dispositivo), $this->nombres, true);
    }

    /**
     * Dónde empieza y termina cada trayectoria, como índices `[primera, última]` de las lecturas.
     *
     * - Un hueco de más de `HUECO_SEGUNDOS`: termina en la lectura de antes y la de después empieza otra.
     * - Una parada de más de `PARADA_SEGUNDOS` (de la primera lectura detenida a la que arranca):
     *   termina al detenerse y la siguiente empieza en la última lectura detenida. Las de en medio no son de ninguna.
     * - Lo que nunca se movió (el teléfono quieto en la mesa) no es una trayectoria.
     *
     * @param list<array{int, int}> $lecturas `[instante, velocidadKmh]`, en orden
     * @return list<array{int, int}>
     */
    public static function partir(array $lecturas): array
    {
        $tramos = [];
        $desde = 0;
        $parada = null;
        foreach ($lecturas as $k => [$t, $velocidad]) {
            $siguiente = $lecturas[$k + 1] ?? null;
            $hueco = null === $siguiente || $siguiente[0] - $t > self::HUECO_SEGUNDOS;
            if ($velocidad < HistorialGps::DETENIDO_KMH) {
                $parada ??= $k;
                // La parada acaba en su última lectura: la siguiente arranca o no hay siguiente.
                if ($hueco || $siguiente[1] >= HistorialGps::DETENIDO_KMH) {
                    $arranca = $hueco ? $t : $siguiente[0];
                    if ($arranca - $lecturas[$parada][0] > self::PARADA_SEGUNDOS) {
                        $tramos[] = [$desde, $parada];
                        $desde = $k;
                    }
                    $parada = null;
                }
            }
            if ($hueco) {
                $tramos[] = [$desde, $k];
                $desde = $k + 1;
            }
        }

        return array_values(array_filter($tramos, static function (array $tramo) use ($lecturas): bool {
            for ($i = $tramo[0]; $i <= $tramo[1]; ++$i) {
                if ($lecturas[$i][1] >= HistorialGps::DETENIDO_KMH) {
                    return true;
                }
            }

            return false;
        }));
    }

    /** Guarda la lectura como `HistorialGps::registrar` (sin repetir, y quieto, una cada tanto). */
    public function registrar(string $dispositivo, Posicion $p): bool
    {
        $velocidad = (int) round(min(max($p->velocidadKmh, 0.0), 300.0));
        $parametros = [
            'dispositivo' => self::normalizar($dispositivo),
            'instante' => $p->instante,
            'lat' => (string) $p->lat,
            'lng' => (string) $p->lng,
            'rumbo' => ((int) round($p->rumbo)) % 360,
            'velocidad' => $velocidad,
        ];
        $quieto = '';
        if ($velocidad < HistorialGps::DETENIDO_KMH) {
            $quieto = <<<'SQL'
                WHERE NOT EXISTS (
                    SELECT 1 FROM lectura_gps_prueba u
                    WHERE u.dispositivo = CAST(:dispositivo AS VARCHAR)
                      AND u.instante > to_timestamp(CAST(:desde AS BIGINT)) AND u.instante <= to_timestamp(CAST(:instante AS BIGINT))
                      AND u.velocidad_kmh < CAST(:detenido AS SMALLINT)
                      AND abs(u.lat - CAST(:lat AS DOUBLE PRECISION)) < CAST(:sitio AS DOUBLE PRECISION)
                      AND abs(u.lng - CAST(:lng AS DOUBLE PRECISION)) < CAST(:sitio AS DOUBLE PRECISION))
                SQL;
            $parametros += ['desde' => $p->instante - HistorialGps::QUIETO_SEGUNDOS, 'detenido' => HistorialGps::DETENIDO_KMH, 'sitio' => (string) HistorialGps::MISMO_SITIO_GRADOS];
        }

        return $this->db->executeStatement(<<<SQL
            INSERT INTO lectura_gps_prueba (dispositivo, instante, lat, lng, rumbo, velocidad_kmh)
            SELECT CAST(:dispositivo AS VARCHAR), to_timestamp(CAST(:instante AS BIGINT)), CAST(:lat AS DOUBLE PRECISION), CAST(:lng AS DOUBLE PRECISION),
                   CAST(:rumbo AS SMALLINT), CAST(:velocidad AS SMALLINT)
            $quieto
            ON CONFLICT (dispositivo, instante) DO NOTHING
            SQL, $parametros) > 0;
    }

    /**
     * Las trayectorias que empiezan ese día (hora local), de todos los teléfonos, en orden.
     *
     * @return list<array{dispositivo: string, desde: int, hasta: int, lecturas: int}>
     */
    public function delDia(\DateTimeImmutable $dia): array
    {
        $inicio = $dia->setTime(0, 0)->getTimestamp();
        $fin = $dia->setTime(0, 0)->modify('+1 day')->getTimestamp();
        $out = [];
        foreach ($this->nombres as $nombre) {
            // Desde una hora antes del día: así se sabe si su primera lectura sigue una trayectoria de la víspera
            // (o cierra una parada larga que empezó antes).
            $lecturas = $this->lecturas($nombre, $inicio - 3600, $fin + self::MAXIMO_HORAS * 3600);
            foreach (self::partir($lecturas) as [$i, $j]) {
                if ($lecturas[$i][0] >= $inicio && $lecturas[$i][0] < $fin) {
                    $out[] = ['dispositivo' => $nombre, 'desde' => $lecturas[$i][0], 'hasta' => $lecturas[$j][0], 'lecturas' => $j - $i + 1];
                }
            }
        }
        usort($out, static fn(array $a, array $b) => $a['desde'] <=> $b['desde']);

        return $out;
    }

    /**
     * La trayectoria que empieza en `$desde`: sus lecturas hasta el primer hueco o parada larga (o el tope).
     *
     * @return array{dispositivo: string, desde: int, hasta: int, lecturas: int, puntos: list<array{int, float, float, int, int}>}
     */
    public function trayectoria(string $dispositivo, int $desde): array
    {
        $nombre = self::normalizar($dispositivo);
        $filas = $this->db->fetchAllNumeric(<<<'SQL'
            SELECT CAST(EXTRACT(EPOCH FROM instante) AS BIGINT), lat, lng, velocidad_kmh, rumbo
            FROM lectura_gps_prueba
            WHERE dispositivo = :dispositivo AND instante BETWEEN to_timestamp(:desde) AND to_timestamp(:hasta)
            ORDER BY instante
            LIMIT :tope
            SQL,
            ['dispositivo' => $nombre, 'desde' => $desde, 'hasta' => $desde + self::MAXIMO_HORAS * 3600, 'tope' => HistorialGps::MAXIMO_LECTURAS],
            ['desde' => ParameterType::INTEGER, 'hasta' => ParameterType::INTEGER, 'tope' => ParameterType::INTEGER],
        );

        $todas = array_map(static fn(array $f) => [(int) $f[0], round((float) $f[1], 6), round((float) $f[2], 6), (int) $f[3], (int) $f[4]], $filas);
        $primera = self::partir(array_map(static fn(array $p) => [$p[0], $p[3]], $todas))[0] ?? null;
        $puntos = null === $primera ? [] : array_slice($todas, $primera[0], $primera[1] - $primera[0] + 1);

        return [
            'dispositivo' => $nombre,
            'desde' => $puntos[0][0] ?? $desde,
            'hasta' => [] === $puntos ? $desde : $puntos[count($puntos) - 1][0],
            'lecturas' => count($puntos),
            'puntos' => $puntos,
        ];
    }

    /**
     * Los teléfonos con lectura vigente, para el mapa en vivo, con el inicio de su última trayectoria.
     *
     * @return list<array{dispositivo: string, desde: int, posicion: array<string, mixed>}>
     */
    public function enVivo(int $ahora): array
    {
        $out = [];
        foreach ($this->nombres as $nombre) {
            $p = $this->gps->ultima($nombre);
            if (null === $p || $ahora - $p->instante > SeguimientoBuses::GPS_VIGENCIA_SEGUNDOS) {
                continue;
            }
            $lecturas = $this->lecturas($nombre, $ahora - self::EN_VIVO_HORAS * 3600, $ahora + 60);
            $tramos = self::partir($lecturas);
            $ultima = $tramos[count($tramos) - 1] ?? null;
            $out[] = [
                'dispositivo' => $nombre,
                // La en curso o la que terminó al detenerse; sin ninguna (aún no se movió), esta lectura.
                'desde' => null !== $ultima ? $lecturas[$ultima[0]][0] : $p->instante,
                'posicion' => [
                    'lat' => round($p->lat, 6),
                    'lng' => round($p->lng, 6),
                    'rumbo' => round($p->rumbo, 1),
                    'velocidadKmh' => round($p->velocidadKmh, 1),
                    'km' => 0,
                    'estado' => $p->estado,
                    'instante' => $p->instante,
                    'fuente' => $p->fuente,
                ],
            ];
        }

        return $out;
    }

    /** Borra las lecturas de antes de `$antesDe`. Devuelve cuántas. */
    public function purgar(\DateTimeImmutable $antesDe): int
    {
        return (int) $this->db->executeStatement('DELETE FROM lectura_gps_prueba WHERE instante < to_timestamp(:antes)', ['antes' => $antesDe->getTimestamp()], ['antes' => ParameterType::INTEGER]);
    }

    /** @return list<array{int, int}> `[instante, velocidadKmh]` de un teléfono en `[desde, hasta)`, en orden */
    private function lecturas(string $nombre, int $desde, int $hasta): array
    {
        return array_map(static fn(array $f) => [(int) $f[0], (int) $f[1]], $this->db->fetchAllNumeric(
            'SELECT CAST(EXTRACT(EPOCH FROM instante) AS BIGINT), velocidad_kmh FROM lectura_gps_prueba WHERE dispositivo = :dispositivo AND instante >= to_timestamp(:desde) AND instante < to_timestamp(:hasta) ORDER BY instante',
            ['dispositivo' => $nombre, 'desde' => $desde, 'hasta' => $hasta],
            ['desde' => ParameterType::INTEGER, 'hasta' => ParameterType::INTEGER],
        ));
    }
}
