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
 * los huecos. Más de 5 min entre dos lecturas cierra una trayectoria en la
 * primera y empieza otra en la segunda.
 *
 * Lo ve solo quien ve las salidas de todas las empresas (no es de ninguna).
 */
final class TelefonosDePrueba
{
    /** Más que esto entre dos lecturas separa dos trayectorias (por encima de `HistorialGps::QUIETO_SEGUNDOS`: el raleo del quieto no corta). */
    public const HUECO_SEGUNDOS = 300;
    /** Tope de una trayectoria: lo que se lee después de su primera lectura. */
    private const MAXIMO_HORAS = 48;

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
     * Parte lecturas en orden en trayectorias: una nueva tras más de `HUECO_SEGUNDOS` sin lecturas.
     *
     * @param list<int> $instantes epoch (s), en orden
     * @return list<array{desde: int, hasta: int, lecturas: int}>
     */
    public static function partir(array $instantes): array
    {
        $out = [];
        $actual = null;
        foreach ($instantes as $t) {
            if (null !== $actual && $t - $actual['hasta'] <= self::HUECO_SEGUNDOS) {
                $actual['hasta'] = $t;
                ++$actual['lecturas'];
                continue;
            }
            if (null !== $actual) {
                $out[] = $actual;
            }
            $actual = ['desde' => $t, 'hasta' => $t, 'lecturas' => 1];
        }
        if (null !== $actual) {
            $out[] = $actual;
        }

        return $out;
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
            // Desde un hueco antes del día: así se sabe si su primera lectura sigue una trayectoria de la víspera.
            foreach (self::partir($this->instantes($nombre, $inicio - self::HUECO_SEGUNDOS, $fin + self::MAXIMO_HORAS * 3600)) as $t) {
                if ($t['desde'] >= $inicio && $t['desde'] < $fin) {
                    $out[] = ['dispositivo' => $nombre, ...$t];
                }
            }
        }
        usort($out, static fn(array $a, array $b) => $a['desde'] <=> $b['desde']);

        return $out;
    }

    /**
     * La trayectoria que empieza en `$desde`: sus lecturas hasta el primer hueco (o el tope).
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

        $puntos = [];
        foreach ($filas as $f) {
            $t = (int) $f[0];
            if ([] !== $puntos && $t - $puntos[count($puntos) - 1][0] > self::HUECO_SEGUNDOS) {
                break;
            }
            $puntos[] = [$t, round((float) $f[1], 6), round((float) $f[2], 6), (int) $f[3], (int) $f[4]];
        }

        return [
            'dispositivo' => $nombre,
            'desde' => $puntos[0][0] ?? $desde,
            'hasta' => [] === $puntos ? $desde : $puntos[count($puntos) - 1][0],
            'lecturas' => count($puntos),
            'puntos' => $puntos,
        ];
    }

    /**
     * Los teléfonos con lectura vigente, para el mapa en vivo, con el inicio de su trayectoria en curso.
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
            $trayectorias = self::partir($this->instantes($nombre, $ahora - self::MAXIMO_HORAS * 3600, $ahora + 60));
            $ultima = $trayectorias[count($trayectorias) - 1] ?? null;
            $out[] = [
                'dispositivo' => $nombre,
                // Sin la lectura en el historial (aún no o falló al guardarla), empieza en esta.
                'desde' => null !== $ultima && $p->instante - $ultima['hasta'] <= self::HUECO_SEGUNDOS ? $ultima['desde'] : $p->instante,
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

    /** @return list<int> instantes (epoch) de un teléfono en `[desde, hasta)`, en orden */
    private function instantes(string $nombre, int $desde, int $hasta): array
    {
        return array_map(intval(...), $this->db->fetchFirstColumn(
            'SELECT CAST(EXTRACT(EPOCH FROM instante) AS BIGINT) FROM lectura_gps_prueba WHERE dispositivo = :dispositivo AND instante >= to_timestamp(:desde) AND instante < to_timestamp(:hasta) ORDER BY instante',
            ['dispositivo' => $nombre, 'desde' => $desde, 'hasta' => $hasta],
            ['desde' => ParameterType::INTEGER, 'hasta' => ParameterType::INTEGER],
        ));
    }
}
