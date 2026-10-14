<?php

declare(strict_types=1);

namespace App\Tests\Ruta;

use App\Ruta\KilometrosCarretera;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** Km por carretera desde OSRM. */
final class KilometrosCarreteraTest extends TestCase
{
    public function testConvierteLaDistanciaDeOsrmADeKilometrosYPideLongitudPrimero(): void
    {
        $pedido = null;
        $http = new MockHttpClient(function (string $metodo, string $url) use (&$pedido): MockResponse {
            $pedido = [$metodo, $url];

            return new MockResponse(json_encode(["code" => "Ok", "routes" => [["distance" => 153250.4]]]));
        });

        $km = (new KilometrosCarretera($http, "https://osrm.test/"))->entre(14.6, -90.5, 14.9, -91.1);

        $this->assertEqualsWithDelta(153.2504, $km, 0.0001);
        $this->assertSame("GET", $pedido[0]);
        $this->assertStringStartsWith("https://osrm.test/route/v1/driving/-90.500000,14.600000;-91.100000,14.900000", $pedido[1]);
    }

    public function testSinCoordenadasNoConsultaYDevuelveNulo(): void
    {
        $llamadas = 0;
        $http = new MockHttpClient(function () use (&$llamadas): MockResponse {
            $llamadas++;

            return new MockResponse("{}");
        });

        $this->assertNull((new KilometrosCarretera($http, "https://osrm.test"))->entre(14.6, null, 14.9, -91.1));
        $this->assertSame(0, $llamadas);
    }

    public function testRespuestaNoOkODeErrorDevuelveNulo(): void
    {
        $http = new MockHttpClient([
            new MockResponse(json_encode(["code" => "NoRoute", "routes" => []])),
            new MockResponse("", ["http_code" => 503]),
            new MockResponse("<html>caído</html>"),
        ]);
        $servicio = new KilometrosCarretera($http, "https://osrm.test");

        $this->assertNull($servicio->entre(14.6, -90.5, 14.9, -91.1), "sin ruta");
        $this->assertNull($servicio->entre(14.6, -90.5, 14.9, -91.1), "error HTTP");
        $this->assertNull($servicio->entre(14.6, -90.5, 14.9, -91.1), "cuerpo que no es JSON");
    }

    public function testAcumulaDesdeElOrigenYNoAcumulaSiFaltaUnTramo(): void
    {
        $this->assertSame([0, 50, 120], KilometrosCarretera::acumular([50.4, 69.6]));
        $this->assertSame([0], KilometrosCarretera::acumular([]));
        $this->assertNull(KilometrosCarretera::acumular([50.0, null, 20.0]));
    }
}
