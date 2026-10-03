<?php

declare(strict_types=1);

namespace App\Command;

use App\Enclave\Emparejador;
use App\Seguimiento\Gazetario;
use App\Seguimiento\Geo;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Busca en Nominatim (OpenStreetMap, gratis; máx. 1 petición por segundo)
 * el lugar de cada enclave en Guatemala y guarda sus coordenadas. Solo rellena
 * los que no las tienen; si el nombre local es una errata evidente del lugar
 * hallado (parecido ≥ 0.8) lo corrige. Los enclaves internacionales se buscan
 * en El Salvador, Honduras, Belice y México. Deja el detalle en `var/geocodificacion.tsv`.
 */
#[AsCommand(name: "app:enclave:geocodificar", description: "Rellena latitud/longitud de los enclaves buscando su nombre en Nominatim")]
final class EnclaveGeocodificarCommand
{
    private const URL = 'https://nominatim.openstreetmap.org/search';
    private const PHOTON = 'https://photon.komoot.io/api/';
    private const PAISES_VECINOS = 'gt,sv,hn,bz,mx';

    public function __construct(
        private readonly Connection $db,
        private readonly HttpClientInterface $http,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(param: 'kernel.project_dir')] private readonly string $dir,
    ) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'No escribe en la base, solo informa')] bool $dryRun = false,
    ): int {
        $enclaves = $this->db->fetchAllAssociative('SELECT id, nombre, departamento, latitud, longitud FROM enclave ORDER BY id');
        $cache = [];
        $informe = ["id\testado\tnombre_local\tnombre_nuevo\tlat\tlng\tparecido\tlugar_api\tdistancia_km"];
        $ultima = 0.0;
        /** Coordenadas por lugar (sin acentos/mayúsculas) para dar a sus terminales hermanas ("Aguilar Batres1", "Aguilar Batres Web"). */
        $porLugar = [];
        $pendientes = [];
        $cont = ['coordenadas' => 0, 'renombrados' => 0, 'sin_resultado' => 0, 'ya_tenian' => 0];

        foreach ($enclaves as $e) {
            $nombre = (string) $e['nombre'];
            $bases = Emparejador::bases($nombre, $e['departamento']);
            $base = $bases[0] ?? '';
            $dep = $e['departamento'];
            if ('' === $base || !preg_match('/\p{L}{3}/u', $base) || 'VIRTUAL' === strtoupper($base)) {
                $informe[] = "{$e['id']}\tomitido\t$nombre\t\t\t\t\t\t";
                continue;
            }

            $hallado = null;
            foreach ($bases as $candidata) {
                $clave = $candidata . '|' . $dep;
                if (!array_key_exists($clave, $cache)) {
                    $cache[$clave] = $this->buscar($candidata, $dep, $ultima);
                }
                if ($hallado = $cache[$clave]) {
                    $base = $candidata;
                    break;
                }
            }

            if (null === $hallado) {
                if (null !== $e['latitud']) {
                    $porLugar[Gazetario::normalizar($base)] = [(float) $e['latitud'], (float) $e['longitud']];
                } else {
                    $pendientes[] = [$e, $base];
                }
                ++$cont['sin_resultado'];
                $io->writeln(sprintf('<comment>%4d %-45s sin resultado</comment>', $e['id'], $nombre));
                $informe[] = "{$e['id']}\tsin_resultado\t$nombre\t\t\t\t\t\t";
                continue;
            }

            $nuevo = Emparejador::nombreCorregido($nombre, $base, $hallado['nombre']);
            $nuevo = null === $nuevo ? null : trim((string) preg_replace('/\s+/u', ' ', $nuevo));
            $tenia = null !== $e['latitud'] && null !== $e['longitud'];
            $dist = $tenia ? round(Geo::distanciaKm((float) $e['latitud'], (float) $e['longitud'], $hallado['lat'], $hallado['lng']), 1) : null;

            if (!$dryRun) {
                if ($nuevo) {
                    $this->db->update('enclave', ['nombre' => mb_substr($nuevo, 0, 255)], ['id' => $e['id']]);
                }
                if (!$tenia) {
                    $this->db->update('enclave', ['latitud' => round($hallado['lat'], 7), 'longitud' => round($hallado['lng'], 7)], ['id' => $e['id']]);
                }
            }
            $porLugar[Gazetario::normalizar($base)] ??= $tenia ? [(float) $e['latitud'], (float) $e['longitud']] : [$hallado['lat'], $hallado['lng']];
            $tenia ? ++$cont['ya_tenian'] : ++$cont['coordenadas'];
            $nuevo && ++$cont['renombrados'];

            $io->writeln(sprintf('%4d %-45s %s%s%s', $e['id'], $nombre, $tenia ? "(ya tenía GPS, a {$dist} km de la API)" : sprintf('%.5f, %.5f', $hallado['lat'], $hallado['lng']), $nuevo ? "  → renombrado: $nuevo" : '', ''));
            $informe[] = implode("\t", [$e['id'], $tenia ? 'ya_tenia' : 'ok', $nombre, $nuevo ?? '', $hallado['lat'], $hallado['lng'], round($hallado['parecido'], 2), $hallado['lugar'], $dist ?? '']);
        }

        foreach ($pendientes as [$e, $base]) {
            $c = $porLugar[Gazetario::normalizar($base)] ?? null;
            if (null === $c) {
                continue;
            }
            if (!$dryRun) {
                $this->db->update('enclave', ['latitud' => round($c[0], 7), 'longitud' => round($c[1], 7)], ['id' => $e['id']]);
            }
            --$cont['sin_resultado'];
            ++$cont['coordenadas'];
            $io->writeln(sprintf('%4d %-45s %.5f, %.5f (de otra terminal del mismo lugar)', $e['id'], $e['nombre'], $c[0], $c[1]));
            $informe[] = implode("\t", [$e['id'], 'hermana', $e['nombre'], '', $c[0], $c[1], '', 'copiado de otra terminal de ' . $base, '']);
        }

        file_put_contents($this->dir . '/var/geocodificacion.tsv', implode("\n", $informe) . "\n");
        $io->success(sprintf('%s: %d coordenadas nuevas, %d nombres corregidos, %d ya tenían GPS, %d sin resultado. Detalle en var/geocodificacion.tsv', $dryRun ? 'Simulación' : 'Hecho', $cont['coordenadas'], $cont['renombrados'], $cont['ya_tenian'], $cont['sin_resultado']));

        return 0;
    }

    /** @return array{nombre: string, lat: float, lng: float, parecido: float, lugar: string}|null */
    private function buscar(string $base, ?string $dep, float &$ultima): ?array
    {
        $internacional = null === $dep || '' === $dep || 'Internacional' === $dep;
        $consultas = $internacional ? [$base] : [$base, "$base, $dep"];
        foreach ($consultas as $q) {
            $query = ['q' => $q, 'format' => 'jsonv2', 'limit' => 10, 'addressdetails' => 1, 'accept-language' => 'es', 'countrycodes' => $internacional ? self::PAISES_VECINOS : 'gt'];
            $r = $this->pedir(self::URL, $query, $ultima);
            if ($internacional) {
                $r = array_values(array_filter($r, static fn(array $x) => ($x['address']['country_code'] ?? '') !== 'gt' && ($x['lat'] ?? 0) > 13 && ($x['lat'] ?? 0) < 19.6 && ($x['lon'] ?? 0) > -93 && ($x['lon'] ?? 0) < -86));
            }
            if ($hallado = Emparejador::elegir($base, $dep, $r)) {
                return $hallado;
            }
        }

        // Respaldo: Photon (OpenStreetMap) tolera erratas que Nominatim no.
        {
            $r = $this->pedir(self::PHOTON, ['q' => $base, 'limit' => 10, 'lat' => 15.5, 'lon' => -90.3], $ultima);
            $r = array_map(static fn(array $f) => [
                'name' => $f['properties']['name'] ?? '',
                'lat' => (string) ($f['geometry']['coordinates'][1] ?? ''),
                'lon' => (string) ($f['geometry']['coordinates'][0] ?? ''),
                'addresstype' => $f['properties']['osm_value'] ?? '',
                'display_name' => ($f['properties']['name'] ?? '') . ', ' . ($f['properties']['state'] ?? ''),
                'address' => ['state' => $f['properties']['state'] ?? ''],
                'country' => $f['properties']['countrycode'] ?? '',
            ], $r['features'] ?? []);
            $r = array_values(array_filter($r, static fn(array $x) => $internacional ? in_array($x['country'], ['SV', 'HN', 'BZ', 'MX'], true) && $x['lat'] > 13 && $x['lat'] < 19.6 && $x['lon'] > -93 && $x['lon'] < -86 : 'GT' === $x['country']));
            if ($hallado = Emparejador::elegir($base, $dep, $r)) {
                return $hallado;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $query */
    private function pedir(string $url, array $query, float &$ultima): array
    {
        $espera = 1.1 - (microtime(true) - $ultima);
        if ($espera > 0) {
            usleep((int) ($espera * 1_000_000));
        }
        $ultima = microtime(true);
        try {
            return $this->http->request('GET', $url, ['query' => $query, 'headers' => ['User-Agent' => 'fdn-enclave-geocoder/1.0 (alcidesrh@gmail.com)'], 'timeout' => 20])->toArray();
        } catch (\Throwable) {
            return [];
        }
    }
}
