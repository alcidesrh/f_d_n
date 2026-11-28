<?php

declare(strict_types=1);

namespace App\Tests\Combustible;

use App\Combustible\CostoCombustible;
use PHPUnit\Framework\TestCase;

/** Costo de combustible de una salida (ADR-073). */
final class CostoCombustibleTest extends TestCase
{
    private const DIA = "2026-11-28";

    public function testCostoConElPrecioDelDia(): void
    {
        // 250 km a 5 km/gal son 50 galones; a Q 42.69 el galón, Q 2,134.50.
        $r = CostoCombustible::calcular(250, 5.0, 42.69, self::DIA, self::DIA);

        $this->assertSame(250, $r["kilometros"]);
        $this->assertEqualsWithDelta(50.0, $r["galones"], 0.001);
        $this->assertEqualsWithDelta(2134.5, $r["costo"], 0.001);
        $this->assertFalse($r["precioEstimado"]);
        $this->assertSame(CostoCombustible::MONEDA, $r["moneda"]);
        $this->assertNull($r["motivo"]);
    }

    public function testUsaElUltimoPrecioAnteriorYLoMarcaComoEstimado(): void
    {
        $r = CostoCombustible::calcular(250, 5.0, 40.0, "2026-11-20", self::DIA);

        $this->assertEqualsWithDelta(2000.0, $r["costo"], 0.001);
        $this->assertSame("2026-11-20", $r["precioFecha"]);
        $this->assertTrue($r["precioEstimado"]);
    }

    public function testRedondeaGalonesYCostoADosDecimales(): void
    {
        // 10 km a 3 km/gal son 3.333… galones; a Q 40, Q 133.33.
        $r = CostoCombustible::calcular(10, 3.0, 40.0, self::DIA, self::DIA);

        $this->assertEqualsWithDelta(3.33, $r["galones"], 0.0001);
        $this->assertEqualsWithDelta(133.33, $r["costo"], 0.0001);
    }

    public function testSinKmDeRutaNoHayCosto(): void
    {
        $r = CostoCombustible::calcular(null, 5.0, 42.69, self::DIA, self::DIA);

        $this->assertNull($r["costo"]);
        $this->assertNull($r["galones"]);
        $this->assertSame(CostoCombustible::SIN_RUTA_KM, $r["motivo"]);
    }

    public function testSinPrecioNoHayCostoYNoHayFechaDePrecio(): void
    {
        $r = CostoCombustible::calcular(250, 5.0, null, null, self::DIA);

        $this->assertNull($r["costo"]);
        $this->assertNull($r["precioFecha"]);
        $this->assertFalse($r["precioEstimado"]);
        $this->assertSame(CostoCombustible::SIN_PRECIO, $r["motivo"]);
    }

    public function testSinBusONRendimientoCeroNoHayCosto(): void
    {
        $this->assertSame(CostoCombustible::SIN_BUS, CostoCombustible::calcular(250, null, 42.69, self::DIA, self::DIA)["motivo"]);
        $this->assertSame(CostoCombustible::SIN_BUS, CostoCombustible::calcular(250, 0.0, 42.69, self::DIA, self::DIA)["motivo"]);
    }

    public function testRutaDeCeroKilometrosCuestaCero(): void
    {
        $r = CostoCombustible::calcular(0, 5.0, 42.69, self::DIA, self::DIA);

        $this->assertEqualsWithDelta(0.0, $r["costo"], 0.001);
        $this->assertNull($r["motivo"]);
    }
}
