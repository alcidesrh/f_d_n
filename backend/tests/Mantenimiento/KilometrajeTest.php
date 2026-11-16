<?php

declare(strict_types=1);

namespace App\Tests\Mantenimiento;

use App\Mantenimiento\Kilometraje;
use PHPUnit\Framework\TestCase;

/** Kilómetros por GPS para el odómetro (ADR-065). */
final class KilometrajeTest extends TestCase
{
    private const T = 1_800_000_000;
    /** Un grado de latitud, en km. */
    private const GRADO = 111.195;

    public function testSumaLosTramos(): void
    {
        // Medio grado al norte en una hora y otro medio en la siguiente: ~111 km a ~56 km/h.
        $r = Kilometraje::de([[self::T, 15.0, -90.0], [self::T + 3600, 15.5, -90.0], [self::T + 7200, 16.0, -90.0]]);

        $this->assertEqualsWithDelta(self::GRADO, $r["km"], 0.05);
        $this->assertSame(3, $r["lecturas"]);
        $this->assertSame(self::T + 7200, $r["ultima"]);
    }

    public function testSinLecturasOConUnaSolaNoSuma(): void
    {
        $this->assertSame(["km" => 0.0, "lecturas" => 0, "ultima" => null], Kilometraje::de([]));
        $this->assertSame(["km" => 0.0, "lecturas" => 1, "ultima" => self::T], Kilometraje::de([[self::T, 15.0, -90.0]]));
    }

    public function testElBusDetenidoNoSumaElTemblorDelGps(): void
    {
        // Toda la noche en el predio, con lecturas que bailan ~10 m.
        $lecturas = [];
        for ($i = 0; $i < 300; $i++) {
            $lecturas[] = [self::T + $i * 120, 15.0 + ($i % 2) * 0.0001, -90.0];
        }
        $r = Kilometraje::de($lecturas);

        $this->assertSame(0.0, $r["km"]);
        $this->assertSame(self::T + 299 * 120, $r["ultima"]);
    }

    public function testDescartaUnaLecturaALaQueNoPudoLlegar(): void
    {
        // Un salto de ~55 km en un minuto y vuelta: no cuenta ni la ida ni el regreso.
        $r = Kilometraje::de([
            [self::T, 15.0, -90.0],
            [self::T + 60, 15.5, -90.0],
            [self::T + 120, 15.001, -90.0],
            [self::T + 180, 15.002, -90.0],
        ]);

        $this->assertEqualsWithDelta(0.222, $r["km"], 0.01);
    }

    public function testDespuesDeEstarDetenidoLaVelocidadSeMideDesdeLaUltimaLecturaEnElSitio(): void
    {
        // Dos horas quieto y, un minuto después de la última lectura ahí, aparece a 55 km: es un salto, no un viaje de dos horas.
        $r = Kilometraje::de([
            [self::T, 15.0, -90.0],
            [self::T + 7200, 15.0, -90.0],
            [self::T + 7260, 15.5, -90.0],
        ]);

        $this->assertSame(0.0, $r["km"]);
    }

    public function testConElGpsApagadoUnRatoCuentaLaCuerda(): void
    {
        // Sin lecturas durante dos horas y reaparece a ~55 km: se suma la línea recta.
        $r = Kilometraje::de([[self::T, 15.0, -90.0], [self::T + 7200, 15.5, -90.0]]);

        $this->assertEqualsWithDelta(self::GRADO / 2, $r["km"], 0.05);
    }
}
