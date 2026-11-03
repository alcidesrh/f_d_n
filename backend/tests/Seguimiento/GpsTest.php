<?php

declare(strict_types=1);

namespace App\Tests\Seguimiento;

use App\Seguimiento\GpsReciente;
use App\Seguimiento\LecturaOsmAnd;
use App\Seguimiento\PlanDeViaje;
use App\Seguimiento\Posicion;
use App\Seguimiento\TrazadoDeRuta;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class GpsTest extends TestCase
{
    private const AHORA = 1_790_000_000;

    public function testLeeParametrosDeTraccarClasico(): void
    {
        $l = LecturaOsmAnd::desde(['id' => ' 123 ', 'lat' => '14.6', 'lon' => '-90.5', 'timestamp' => (string) (self::AHORA - 20), 'speed' => '10', 'bearing' => '370'], null, self::AHORA);

        self::assertSame('123', $l->busCodigo);
        self::assertSame(14.6, $l->posicion->lat);
        self::assertSame(-90.5, $l->posicion->lng);
        self::assertEqualsWithDelta(18.52, $l->posicion->velocidadKmh, 0.001, 'speed viene en nudos');
        self::assertSame(10.0, $l->posicion->rumbo);
        self::assertSame(self::AHORA - 20, $l->posicion->instante);
        self::assertSame(Posicion::EN_RUTA, $l->posicion->estado);
        self::assertSame('gps', $l->posicion->fuente);
    }

    public function testLeeJsonDeTraccarNuevo(): void
    {
        $json = ['device_id' => 'B-7', 'location' => ['timestamp' => '2026-09-21T12:00:00.000Z', 'coords' => ['latitude' => 15.1, 'longitude' => -89.9, 'speed' => 0.5, 'heading' => 90]]];
        $l = LecturaOsmAnd::desde([], $json, self::AHORA);

        self::assertSame('B-7', $l->busCodigo);
        self::assertEqualsWithDelta(1.8, $l->posicion->velocidadKmh, 0.001, 'speed viene en m/s');
        self::assertSame(Posicion::DETENIDO, $l->posicion->estado);
        self::assertSame((new \DateTimeImmutable('2026-09-21T12:00:00Z'))->getTimestamp(), $l->posicion->instante);
    }

    public function testInstanteEnMilisegundosODelFuturo(): void
    {
        $ms = LecturaOsmAnd::desde(['id' => '1', 'lat' => 1, 'lon' => 1, 'timestamp' => (self::AHORA - 5) * 1000], null, self::AHORA);
        $futuro = LecturaOsmAnd::desde(['id' => '1', 'lat' => 1, 'lon' => 1, 'timestamp' => self::AHORA + 3600], null, self::AHORA);
        $sin = LecturaOsmAnd::desde(['id' => '1', 'lat' => 1, 'lon' => 1], null, self::AHORA);

        self::assertSame(self::AHORA - 5, $ms->posicion->instante);
        self::assertSame(self::AHORA, $futuro->posicion->instante);
        self::assertSame(self::AHORA, $sin->posicion->instante);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function tramasInvalidas(): iterable
    {
        yield 'sin id' => [['lat' => 1, 'lon' => 1]];
        yield 'sin lat' => [['id' => '1', 'lon' => 1]];
        yield 'lat fuera de rango' => [['id' => '1', 'lat' => 91, 'lon' => 1]];
        yield 'lon no numérica' => [['id' => '1', 'lat' => 1, 'lon' => 'x']];
    }

    /** @param array<string, mixed> $parametros */
    #[\PHPUnit\Framework\Attributes\DataProvider('tramasInvalidas')]
    public function testRechazaTramasInvalidas(array $parametros): void
    {
        $this->expectException(\InvalidArgumentException::class);
        LecturaOsmAnd::desde($parametros, null, self::AHORA);
    }

    public function testGuardaLaUltimaSinQueUnaAtrasadaLaPise(): void
    {
        $gps = new GpsReciente(new ArrayAdapter());
        $nueva = new Posicion(14.0, -90.0, 0, 50, 0, Posicion::EN_RUTA, self::AHORA, 'gps');
        $vieja = new Posicion(13.0, -91.0, 0, 50, 0, Posicion::EN_RUTA, self::AHORA - 60, 'gps');

        self::assertNull($gps->ultima('123'));
        $gps->guardar('123', $nueva);
        $gps->guardar('123', $vieja);

        self::assertEquals($nueva, $gps->ultima('123'));
        self::assertEquals($nueva, $gps->ultima(' 123 '), 'el código se compara sin espacios');
        self::assertNull($gps->ultima('124'));
    }

    public function testUbicaLaLecturaEnLosKmDeLaRuta(): void
    {
        $puntos = TrazadoDeRuta::trazar([
            ['id' => 1, 'nombre' => 'Guatemala', 'latitude' => '14.631349', 'longitude' => '-90.512406', 'departamento' => 'Guatemala'],
            ['id' => 3, 'nombre' => 'Santa Elena, Flores, Peten', 'latitude' => '16.920841', 'longitude' => '-89.893606', 'departamento' => 'Petén'],
        ], 480.0);
        self::assertNotNull($puntos);
        $plan = PlanDeViaje::construir($puntos, new \DateTimeImmutable('2026-10-02 06:00:00'), 1);

        self::assertEqualsWithDelta(0.0, $plan->kmMasCercano(14.631349, -90.512406), 0.01);
        self::assertEqualsWithDelta(480.0, $plan->kmMasCercano(16.920841, -89.893606), 0.01);
        // A mitad de camino, algo desviado de la recta: la mitad de los km.
        self::assertEqualsWithDelta(240.0, $plan->kmMasCercano((14.631349 + 16.920841) / 2 + 0.02, (-90.512406 - 89.893606) / 2 - 0.05), 15.0);
        // Antes del origen: se queda en 0.
        self::assertSame(0.0, $plan->kmMasCercano(14.0, -90.7));
    }
}
