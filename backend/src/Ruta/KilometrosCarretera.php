<?php

declare(strict_types=1);

namespace App\Ruta;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Km por carretera entre dos lugares: pregunta al servicio de rutas
 * OSRM (`route/v1/driving`) por las coordenadas de los enclaves. Si falta una
 * coordenada o el servicio no responde, devuelve nulo y la ruta conserva los
 * km que escribió la persona.
 *
 * La URL sale de `OSRM_URL` (por defecto el servidor de demostración público).
 */
final class KilometrosCarretera
{
    public function __construct(
        private readonly HttpClientInterface $http,
        #[Autowire(env: "default:ruta_osrm_url_defecto:OSRM_URL")]
        private readonly string $url,
    ) {}

    /** Km por carretera entre dos puntos, o nulo si no se pudo calcular. */
    public function entre(?float $latA, ?float $lngA, ?float $latB, ?float $lngB): ?float
    {
        if ($latA === null || $lngA === null || $latB === null || $lngB === null) {
            return null;
        }
        // OSRM recibe longitud,latitud.
        $ruta = sprintf("%s/route/v1/driving/%F,%F;%F,%F", rtrim($this->url, "/"), $lngA, $latA, $lngB, $latB);
        try {
            $datos = $this->http->request("GET", $ruta, ["query" => ["overview" => "false"], "timeout" => 10])->toArray(false);
        } catch (\Throwable) {
            return null;
        }
        $metros = $datos["routes"][0]["distance"] ?? null;

        return ($datos["code"] ?? null) === "Ok" && is_numeric($metros) ? (float) $metros / 1000 : null;
    }

    /**
     * Km acumulados desde el origen, a partir de los tramos entre paradas
     * consecutivas (`tramos[i]` va de la parada i a la i+1). Nulo si falta
     * alguno: una ruta a medias no tiene km confiables.
     *
     * @param list<?float> $tramos
     *
     * @return list<int>|null
     */
    public static function acumular(array $tramos): ?array
    {
        $total = 0.0;
        $acumulados = [0];
        foreach ($tramos as $tramo) {
            if ($tramo === null) {
                return null;
            }
            $total += $tramo;
            $acumulados[] = (int) round($total);
        }

        return $acumulados;
    }
}
