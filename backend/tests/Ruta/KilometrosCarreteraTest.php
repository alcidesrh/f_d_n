<?php

declare(strict_types=1);

namespace App\Tests\Ruta;

use App\Mapa\Valhalla;
use App\Ruta\KilometrosCarretera;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** Km por carretera desde el Valhalla propio. */
final class KilometrosCarreteraTest extends TestCase
{
    public function testDevuelveLosKmDeValhallaEntreLosDosPuntos(): void
    {
        $lugares = null;
        $http = new MockHttpClient(function (string $metodo, string $url, array $opciones) use (&$lugares): MockResponse {
            $lugares = json_decode($opciones["body"], true)["locations"];

            return new MockResponse(json_encode(["trip" => ["status" => 0, "summary" => ["length" => 153.2504, "time" => 9000], "legs" => []]]));
        });

        $km = (new KilometrosCarretera(new Valhalla($http, "http://valhalla.test")))->entre(14.6, -90.5, 14.9, -91.1);

        $this->assertEqualsWithDelta(153.2504, $km, 0.0001);
        $this->assertSame([["lat" => 14.6, "lon" => -90.5], ["lat" => 14.9, "lon" => -91.1]], $lugares);
    }

    public function testSinCoordenadasNoConsultaYDevuelveNulo(): void
    {
        $llamadas = 0;
        $http = new MockHttpClient(function () use (&$llamadas): MockResponse {
            $llamadas++;

            return new MockResponse("{}");
        });

        $this->assertNull((new KilometrosCarretera(new Valhalla($http, "http://valhalla.test")))->entre(14.6, null, 14.9, -91.1));
        $this->assertSame(0, $llamadas);
    }

    public function testSinRutaDevuelveNulo(): void
    {
        $http = new MockHttpClient(new MockResponse(json_encode(["error_code" => 442]), ["http_code" => 400]));

        $this->assertNull((new KilometrosCarretera(new Valhalla($http, "http://valhalla.test")))->entre(14.6, -90.5, 14.9, -91.1));
    }

    public function testAcumulaDesdeElOrigenYNoAcumulaSiFaltaUnTramo(): void
    {
        $this->assertSame([0, 50, 120], KilometrosCarretera::acumular([50.4, 69.6]));
        $this->assertSame([0], KilometrosCarretera::acumular([]));
        $this->assertNull(KilometrosCarretera::acumular([50.0, null, 20.0]));
    }
}
