<?php

declare(strict_types=1);

namespace App\Mapa;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Rutas por carretera con el Valhalla propio (ADR-055): el contenedor `valhalla`,
 * con el grafo que arma `make mapas` desde OpenStreetMap. Se pide con el perfil
 * `bus` (respeta las vías reservadas a buses o prohibidas para ellos).
 *
 * La URL sale de `VALHALLA_URL` (por defecto el contenedor, solo visible en la red de Docker).
 * Si Valhalla no está corriendo o no encuentra camino, devuelve nulo: quien llama decide.
 */
final class Valhalla
{
    public function __construct(
        private readonly HttpClientInterface $http,
        #[Autowire(env: "default:mapa_valhalla_url_defecto:VALHALLA_URL")]
        private readonly string $url,
    ) {}

    /**
     * Recorrido por carretera que pasa por los puntos en orden, o nulo si no se pudo calcular.
     *
     * @param list<array{float, float}> $puntos [latitud, longitud]; al menos dos
     */
    public function recorrido(array $puntos): ?Recorrido
    {
        if (count($puntos) < 2) {
            return null;
        }
        $consulta = [
            "locations" => array_map(static fn (array $p): array => ["lat" => $p[0], "lon" => $p[1]], $puntos),
            "costing" => "bus",
            "units" => "kilometers",
            // Solo distancia, tiempo y trazado: sin instrucciones de giro.
            "directions_type" => "none",
        ];
        try {
            $datos = $this->http->request("POST", rtrim($this->url, "/") . "/route", ["json" => $consulta, "timeout" => 10])->toArray(false);
        } catch (\Throwable) {
            return null;
        }
        $viaje = $datos["trip"] ?? null;
        $km = $viaje["summary"]["length"] ?? null;
        $segundos = $viaje["summary"]["time"] ?? null;
        if (!is_array($viaje) || ($viaje["status"] ?? null) !== 0 || !is_numeric($km) || !is_numeric($segundos)) {
            return null;
        }
        $trazado = [];
        foreach ($viaje["legs"] ?? [] as $tramo) {
            try {
                $puntosTramo = Polilinea::decodificar((string) ($tramo["shape"] ?? ""));
            } catch (\InvalidArgumentException) {
                return null;
            }
            // Cada tramo empieza donde terminó el anterior: el punto repetido sobra.
            if ($trazado !== [] && $puntosTramo !== []) {
                array_shift($puntosTramo);
            }
            array_push($trazado, ...$puntosTramo);
        }

        return new Recorrido((float) $km, (float) $segundos, $trazado);
    }
}
