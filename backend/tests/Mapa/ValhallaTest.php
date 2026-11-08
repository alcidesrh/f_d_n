<?php

declare(strict_types=1);

namespace App\Tests\Mapa;

use App\Mapa\Valhalla;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** Rutas por carretera con el Valhalla propio (ADR-055). */
final class ValhallaTest extends TestCase
{
    public function testPideLaRutaEnBusYDevuelveKmTiempoYTrazadoSinRepetirElPuntoEntreTramos(): void
    {
        $pedido = null;
        $http = new MockHttpClient(function (string $metodo, string $url, array $opciones) use (&$pedido): MockResponse {
            $pedido = [$metodo, $url, json_decode($opciones["body"], true)];

            return new MockResponse(json_encode(["trip" => [
                "status" => 0,
                "units" => "kilometers",
                "summary" => ["length" => 153.25, "time" => 9000.5],
                "legs" => [
                    ["shape" => "eyf|ZbgbskDkDjFmz}q@oqjG"],
                    ["shape" => "_zeo\\~{vjkD_ry@~qy@"],
                ],
            ]]));
        });

        $recorrido = (new Valhalla($http, "http://valhalla.test:8002/"))->recorrido([[14.6349, -90.5069], [15.47, -90.37], [15.5, -90.4]]);

        $this->assertNotNull($recorrido);
        $this->assertSame(153.25, $recorrido->km);
        $this->assertSame(9000.5, $recorrido->segundos);
        $this->assertEqualsWithDelta(
            [[14.634915, -90.506882], [14.635001, -90.507], [15.47, -90.37], [15.5, -90.4]],
            $recorrido->trazado,
            1e-9,
        );
        $this->assertSame("POST", $pedido[0]);
        $this->assertSame("http://valhalla.test:8002/route", $pedido[1]);
        $this->assertSame("bus", $pedido[2]["costing"]);
        $this->assertSame("kilometers", $pedido[2]["units"]);
        $this->assertSame(["lat" => 14.6349, "lon" => -90.5069], $pedido[2]["locations"][0]);
        $this->assertCount(3, $pedido[2]["locations"]);
    }

    public function testConMenosDeDosPuntosNoConsulta(): void
    {
        $llamadas = 0;
        $http = new MockHttpClient(function () use (&$llamadas): MockResponse {
            $llamadas++;

            return new MockResponse("{}");
        });

        $this->assertNull((new Valhalla($http, "http://valhalla.test"))->recorrido([[14.6, -90.5]]));
        $this->assertSame(0, $llamadas);
    }

    public function testSinCaminoErrorOTrazadoRotoDevuelveNulo(): void
    {
        $http = new MockHttpClient([
            new MockResponse(json_encode(["error_code" => 442, "error" => "No path could be found for input"]), ["http_code" => 400]),
            new MockResponse("", ["http_code" => 503]),
            new MockResponse("<html>caído</html>"),
            new MockResponse(json_encode(["trip" => ["status" => 0, "summary" => ["length" => 1.0, "time" => 60], "legs" => [["shape" => "eyf|Z"]]]])),
        ]);
        $valhalla = new Valhalla($http, "http://valhalla.test");
        $puntos = [[14.6, -90.5], [14.9, -91.1]];

        $this->assertNull($valhalla->recorrido($puntos), "sin camino");
        $this->assertNull($valhalla->recorrido($puntos), "error HTTP");
        $this->assertNull($valhalla->recorrido($puntos), "cuerpo que no es JSON");
        $this->assertNull($valhalla->recorrido($puntos), "trazado incompleto");
    }

    public function testSinServicioDevuelveNulo(): void
    {
        $http = new MockHttpClient(static fn (): MockResponse => throw new \Symfony\Component\HttpClient\Exception\TransportException("Could not resolve host: valhalla"));

        $this->assertNull((new Valhalla($http, "http://valhalla:8002"))->recorrido([[14.6, -90.5], [14.9, -91.1]]));
    }
}
