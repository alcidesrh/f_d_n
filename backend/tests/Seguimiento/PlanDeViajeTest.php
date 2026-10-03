<?php

declare(strict_types=1);

namespace App\Tests\Seguimiento;

use App\Seguimiento\Gazetario;
use App\Seguimiento\Geo;
use App\Seguimiento\ParametrosSimulacion;
use App\Seguimiento\PlanDeViaje;
use App\Seguimiento\Posicion;
use App\Seguimiento\TrazadoDeRuta;
use PHPUnit\Framework\TestCase;

final class PlanDeViajeTest extends TestCase
{
    private const PARTIDA = '2026-10-02 06:00:00';

    /** Guatemala → Cruce de Morales (intermedia) → Santa Elena, 480 km. */
    private function plan(int $semilla = 1, ?ParametrosSimulacion $p = null): PlanDeViaje
    {
        $puntos = TrazadoDeRuta::trazar([
            ['id' => 1, 'nombre' => 'Guatemala', 'latitude' => '-90.512406', 'longitude' => '14.631349', 'departamento' => 'Guatemala'],
            ['id' => 4, 'nombre' => 'Cruce de Morales', 'latitude' => null, 'longitude' => null, 'departamento' => 'Izabal'],
            ['id' => 3, 'nombre' => 'Santa Elena, Flores, Peten', 'latitude' => '16.920841', 'longitude' => '-89.893606', 'departamento' => 'Petén'],
        ], 480.0);
        self::assertNotNull($puntos);

        return PlanDeViaje::construir($puntos, new \DateTimeImmutable(self::PARTIDA, new \DateTimeZone('America/Guatemala')), $semilla, $p ?? new ParametrosSimulacion(variacionVelocidad: 0.0));
    }

    private function t(string $hora): int
    {
        return (new \DateTimeImmutable("2026-10-02 $hora", new \DateTimeZone('America/Guatemala')))->getTimestamp();
    }

    public function testNormalizaLatitudYLongitudIntercambiadas(): void
    {
        self::assertSame([14.631349, -90.512406], TrazadoDeRuta::normalizarGps('-90.512406', '14.631349'));
        self::assertSame([16.920841, -89.893606], TrazadoDeRuta::normalizarGps('16.920841', '-89.893606'));
        self::assertNull(TrazadoDeRuta::normalizarGps(null, null));
        self::assertNull(TrazadoDeRuta::normalizarGps('0', '0'));
    }

    public function testLosKilometrosSumanLosDeLaRuta(): void
    {
        $plan = $this->plan();
        self::assertEqualsWithDelta(480.0, $plan->paradas[2]->km, 0.001);
        self::assertSame(0.0, $plan->paradas[0]->km);
        self::assertSame('gps', $plan->paradas[0]->origenCoordenada);
        self::assertSame('catalogo', $plan->paradas[1]->origenCoordenada);
    }

    public function testAntesDeSalirYDespuesDeLlegar(): void
    {
        $plan = $this->plan();
        $antes = $plan->posicionEn($this->t('05:00:00'));
        self::assertSame(Posicion::POR_SALIR, $antes->estado);
        self::assertEqualsWithDelta(14.631349, $antes->lat, 1e-6);

        $despues = $plan->posicionEn($plan->llegada() + 3600);
        self::assertSame(Posicion::LLEGO, $despues->estado);
        self::assertEqualsWithDelta(16.920841, $despues->lat, 1e-6);
        self::assertEqualsWithDelta(480.0, $despues->km, 0.001);
    }

    public function testAvanzaPorLaRutaConVelocidadMedia(): void
    {
        $plan = $this->plan();
        $una = $plan->posicionEn($this->t('07:00:00'));
        self::assertSame(Posicion::EN_RUTA, $una->estado);
        self::assertEqualsWithDelta(68.0, $una->km, 0.5);
        self::assertSame(68.0, $una->velocidadKmh);

        $dos = $plan->posicionEn($this->t('08:00:00'));
        self::assertGreaterThan($una->km, $dos->km);
        // Va hacia el norte-noreste: Guatemala → Cruce de Morales.
        self::assertGreaterThan(0, $dos->rumbo);
        self::assertLessThan(90, $dos->rumbo);
    }

    public function testSeDetieneEnLaEstacionIntermediaYDescansa(): void
    {
        $plan = $this->plan();
        $inter = $plan->paradas[1];
        // 480 km ≥ 300: descanso de 20 min más los 4 de parada.
        self::assertSame(24 * 60, $inter->salida - $inter->llegada);

        $p = $plan->posicionEn($inter->llegada + 60);
        self::assertSame(Posicion::DETENIDO, $p->estado);
        self::assertSame(0.0, $p->velocidadKmh);
        self::assertEqualsWithDelta($inter->lat, $p->lat, 1e-9);

        self::assertSame(Posicion::EN_RUTA, $plan->posicionEn($inter->salida + 60)->estado);
        self::assertSame('Santa Elena, Flores, Peten', $plan->proximaParada($inter->salida + 60)?->nombre);
        self::assertNull($plan->proximaParada($plan->llegada() + 1));
    }

    public function testLaVariacionDeVelocidadEsEstablePorSalida(): void
    {
        $p = new ParametrosSimulacion(variacionVelocidad: 0.08);
        self::assertSame($this->plan(7, $p)->llegada(), $this->plan(7, $p)->llegada());
        $velocidades = array_map(fn(int $s) => $this->plan($s, $p)->velocidadKmh, range(1, 30));
        self::assertGreaterThan(1.0, max($velocidades) - min($velocidades));
        self::assertGreaterThanOrEqual(68.0 * 0.92 - 1e-9, min($velocidades));
        self::assertLessThanOrEqual(68.0 * 1.08 + 1e-9, max($velocidades));
    }

    private static function est(int $id, string $nombre, ?string $lat, ?string $lng, ?string $dep = null): array
    {
        return ['id' => $id, 'nombre' => $nombre, 'latitude' => $lat, 'longitude' => $lng, 'departamento' => $dep];
    }

    public function testSinUbicarOrigenODestinoNoSeTraza(): void
    {
        self::assertNull(TrazadoDeRuta::trazar([self::est(1, 'Lugar X', null, null, 'Internacional'), self::est(2, 'Quetzaltenango', null, null)], 100.0));
        self::assertNull(TrazadoDeRuta::trazar([self::est(1, 'Quetzaltenango', null, null), self::est(2, 'Lugar Y', null, null)], 100.0));
    }

    public function testDescartaIntermediasSoloUbicadasPorDepartamento(): void
    {
        $p = TrazadoDeRuta::trazar([
            self::est(1, 'Guatemala', '14.63', '-90.51'),
            self::est(2, 'Lugar Raro', null, null, 'Petén'),
            self::est(3, 'Santa Elena', '16.92', '-89.89'),
        ], 480.0);
        self::assertSame(['Guatemala', 'Santa Elena'], array_column($p, 'nombre'));
        self::assertEqualsWithDelta(480.0, $p[1]['km'], 1e-6);
    }

    public function testConservaOrigenYDestinoAunqueSoloTenganDepartamento(): void
    {
        $p = TrazadoDeRuta::trazar([self::est(1, 'Lugar Raro', null, null, 'Jutiapa'), self::est(2, 'Zacapa', null, null)], 0.0);
        self::assertSame('departamento', $p[0]['origen']);
        self::assertSame('catalogo', $p[1]['origen']);
    }

    public function testFundeEstacionesDelMismoSitio(): void
    {
        $p = TrazadoDeRuta::trazar([
            self::est(1, 'Guatemala', '14.63', '-90.51'),
            self::est(2, 'Tecun Uman', null, null),
            self::est(3, 'Tecun Uman 2', null, null),
            self::est(4, 'Tecun Uman 3', null, null),
        ], 0.0);
        // El destino repetido absorbe a las anteriores iguales: Guatemala → Tecun Uman 3.
        self::assertSame(['Guatemala', 'Tecun Uman 3'], array_column($p, 'nombre'));
    }

    public function testDescartaIntermediasQueObliganAUnDesvioEnorme(): void
    {
        $p = TrazadoDeRuta::trazar([
            self::est(1, 'Puerto Cortez', null, null),
            self::est(2, 'Tegucigalpa', null, null),
            self::est(3, 'San Pedro Sula', null, null),
        ], 0.0);
        self::assertSame(['Puerto Cortez', 'San Pedro Sula'], array_column($p, 'nombre'));
    }

    public function testSinKilometrosUsaLaRectaPorFactorCarretera(): void
    {
        $p = TrazadoDeRuta::trazar([self::est(1, 'A', '14', '-90'), self::est(2, 'B', '16', '-90')], 0.0);
        self::assertEqualsWithDelta(Geo::distanciaKm(14, -90, 16, -90) * 1.3, $p[1]['km'], 0.01);
    }

    public function testGazetario(): void
    {
        self::assertSame([14.8333, -91.5167], Gazetario::porNombre('Quetzaltenango, Xela Starbus'));
        self::assertSame([14.9167, -91.46], Gazetario::porNombre('Cuatro caminos xela'));
        self::assertSame([14.6469, -90.451], Gazetario::porNombre('Centra Norte a Quetzaltenango, Xela'));
        self::assertNull(Gazetario::porNombre('Tiucal'));
        self::assertSame([14.2833, -89.8917], Gazetario::porDepartamento('Jutiapa'));
        self::assertSame([16.9208, -89.8936], Gazetario::porDepartamento('Petén'));
        self::assertNull(Gazetario::porDepartamento('Internacional'));
    }

    public function testDistanciaYRumbo(): void
    {
        // Guatemala → Santa Elena ≈ 263 km en línea recta.
        self::assertEqualsWithDelta(263, Geo::distanciaKm(14.631349, -90.512406, 16.920841, -89.893606), 10);
        self::assertEqualsWithDelta(90.0, Geo::rumbo(0, 0, 0, 1), 0.01);
        self::assertEqualsWithDelta(0.0, Geo::rumbo(0, 0, 1, 0), 0.01);
    }
}
