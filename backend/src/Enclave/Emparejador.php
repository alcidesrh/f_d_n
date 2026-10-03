<?php

declare(strict_types=1);

namespace App\Enclave;

use App\Seguimiento\Gazetario;
use App\Seguimiento\Geo;

/**
 * Reglas puras para emparejar el nombre de un enclave con los resultados de un
 * geocodificador (Nominatim): qué buscar, qué resultado aceptar y, si el nombre
 * local tiene una errata evidente, cómo corregirlo.
 */
final class Emparejador
{
    /** Parecido mínimo (0..1) entre el nombre buscado y el del resultado. */
    public const UMBRAL = 0.7;

    /** Calificativos que el legado añade al lugar y que no forman parte de su nombre. */
    private const CALIFICATIVOS = '/(?:\s+(?:starbus|web|ida y vuelta)\b|\s*\d+\s*$)/iu';

    /**
     * Lugares que se buscan, por orden de preferencia: los segmentos del nombre
     * (separados por coma o punto y coma) sin calificativos como "StarBus", "Web" o
     * números de agencia, y sin los que son el propio departamento ("Gualan, Zacapa"
     * → Gualan). Si el primer segmento es el departamento y hay más, va el último
     * ("Quetzaltenango, Xela" → Xela, Quetzaltenango). Un departamento al final del
     * segmento se quita ("Coban Alta Verapaz" → Coban).
     *
     * @return list<string>
     */
    public static function bases(string $nombre, ?string $departamento = null): array
    {
        $dep = null === $departamento ? '' : Gazetario::normalizar($departamento);
        $segmentos = array_values(array_filter(array_map('trim', preg_split('/[,;]/', $nombre) ?: [$nombre]), static fn(string $s) => '' !== $s));
        $limpios = [];
        foreach ($segmentos as $i => $seg) {
            $seg = preg_replace(self::CALIFICATIVOS, '', $seg) ?? $seg;
            if ('' !== $dep && Gazetario::normalizar($seg) !== $dep) {
                $sin = trim(strtr($departamento, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']));
                foreach (array_unique([$departamento, $sin]) as $d) {
                    $seg = preg_replace('/\s+' . preg_quote($d, '/') . '$/iu', '', $seg) ?? $seg;
                }
            }
            $limpios[$i] = trim(preg_replace('/\s+/u', ' ', $seg) ?? $seg);
        }

        $bases = [];
        foreach ($limpios as $i => $seg) {
            if ('' !== $seg && (0 === $i || '' === $dep || Gazetario::normalizar($seg) !== $dep) && (Gazetario::normalizar($seg) !== $dep || 0 === $i)) {
                $bases[] = $seg;
            }
        }
        // El departamento (solo si es el primer segmento) queda como último recurso.
        if (isset($limpios[0]) && '' !== $dep && Gazetario::normalizar($limpios[0]) === $dep && count($bases) > 1) {
            $bases = [...array_slice($bases, 1), $bases[0]];
        }

        return $bases;
    }

    /** Parecido 0..1 insensible a mayúsculas, acentos y signos. */
    public static function similitud(string $a, string $b): float
    {
        $a = Gazetario::normalizar($a);
        $b = Gazetario::normalizar($b);
        $max = max(strlen($a), strlen($b));
        if (0 === $max) {
            return 0.0;
        }

        return 1 - levenshtein($a, $b) / $max;
    }

    /**
     * Elige el mejor resultado para la búsqueda. Si el enclave tiene departamento
     * (en Guatemala) el resultado debe estar en ese departamento; los internacionales
     * no tienen esa restricción pero piden un parecido casi exacto.
     *
     * @param list<array<string, mixed>> $resultados respuesta `jsonv2` con `addressdetails=1`
     * @return array{nombre: string, lat: float, lng: float, parecido: float, lugar: string}|null
     */
    public static function elegir(string $base, ?string $departamento, array $resultados): ?array
    {
        $internacional = null === $departamento || '' === $departamento || 'Internacional' === $departamento;
        $minimo = $internacional ? 0.9 : self::UMBRAL;
        $mejor = null;

        foreach ($resultados as $r) {
            $nombre = (string) ($r['name'] ?? explode(',', (string) ($r['display_name'] ?? ''))[0]);
            if ('' === $nombre || !isset($r['lat'], $r['lon'])) {
                continue;
            }
            $nombre = self::sinPrefijo($nombre);
            $parecido = self::similitud($base, $nombre);
            if ($parecido < $minimo || Gazetario::normalizar($base)[0] !== (Gazetario::normalizar($nombre)[0] ?? '')) {
                continue;
            }
            if (!$internacional && !self::enDepartamento($r, (string) $departamento)) {
                continue;
            }
            $orden = [-$parecido, self::rangoTipo((string) ($r['addresstype'] ?? '')), -(float) ($r['importance'] ?? 0)];
            if (null === $mejor || $orden < $mejor['orden']) {
                $mejor = [
                    'orden' => $orden,
                    'nombre' => $nombre,
                    'lat' => (float) $r['lat'],
                    'lng' => (float) $r['lon'],
                    'parecido' => $parecido,
                    'lugar' => (string) ($r['display_name'] ?? ''),
                ];
            }
        }

        if (null === $mejor) {
            return null;
        }
        // Un internacional sin departamento con que confirmarlo no vale si hay otro igual de parecido lejos ("San Cristóbal").
        if ($internacional) {
            foreach ($resultados as $r) {
                if (isset($r['lat'], $r['lon']) && self::similitud($base, self::sinPrefijo((string) ($r['name'] ?? ''))) >= $mejor['parecido'] - 0.02
                    && Geo::distanciaKm($mejor['lat'], $mejor['lng'], (float) $r['lat'], (float) $r['lon']) > 50) {
                    return null;
                }
            }
        }
        unset($mejor['orden']);

        return $mejor;
    }

    /**
     * Nombre corregido: cambia el lugar (`$base`) por el del resultado conservando
     * lo demás ("Tecun uman 3" → "Tecún Umán 3"). Solo si es una errata (parecido alto
     * pero no idéntico); una diferencia de mayúsculas no cuenta.
     */
    public static function nombreCorregido(string $nombre, string $base, string $nombreApi): ?string
    {
        if ('' === $base || self::similitud($base, $nombreApi) < self::UMBRAL || mb_strtolower($base) === mb_strtolower($nombreApi)) {
            return null;
        }
        $pos = mb_stripos($nombre, $base);
        if (false === $pos) {
            return null;
        }
        $nuevo = mb_substr($nombre, 0, $pos) . $nombreApi . mb_substr($nombre, $pos + mb_strlen($base));

        return $nuevo === $nombre ? null : $nuevo;
    }

    /** "Ciudad de Guatemala" → "Guatemala"; "Municipio de Poptún" → "Poptún". */
    private static function sinPrefijo(string $nombre): string
    {
        return preg_replace('/^(?:ciudad(?: de)?|municipio de|departamento de|aldea|caserio|canton)\s+/iu', '', $nombre) ?? $nombre;
    }

    private static function enDepartamento(array $r, string $departamento): bool
    {
        $esperado = Gazetario::normalizar($departamento);
        $estado = Gazetario::normalizar((string) ($r['address']['state'] ?? ''));
        // "Departamento de Petén" / "Petén"; cualquiera de los dos contiene al otro.
        return '' !== $estado && '' !== $esperado && (str_contains($estado, $esperado) || str_contains($esperado, $estado));
    }

    private static function rangoTipo(string $tipo): int
    {
        return match ($tipo) {
            'city', 'town', 'village', 'municipality' => 0,
            'hamlet', 'suburb', 'city_district', 'quarter', 'neighbourhood', 'locality', 'isolated_dwelling' => 1,
            default => 3,
        };
    }
}
