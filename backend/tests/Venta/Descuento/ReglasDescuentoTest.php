<?php

declare(strict_types=1);

namespace App\Tests\Venta\Descuento;

use App\Venta\Descuento\Descuento;
use App\Venta\Descuento\ReglasDescuento as R;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\TestCase;

/** Tipos de pasajero y promociones (ADR-066). */
final class ReglasDescuentoTest extends TestCase
{
    private const SALIDA = ["fecha" => "2026-12-10", "empresa" => 1, "ruta" => 5, "busClase" => 2];

    /** @return array{activa: bool, codigo: ?string, desde: string, hasta: string, empresa: ?int, ruta: ?int, busClase: ?int} */
    private static function promo(array $cambios = []): array
    {
        return [...["activa" => true, "codigo" => null, "desde" => "2026-12-01", "hasta" => "2026-12-15", "empresa" => null, "ruta" => null, "busClase" => null], ...$cambios];
    }

    public function testLaVigenciaEsPorLaFechaDelViajeConAmbosExtremos(): void
    {
        $this->assertTrue(R::vale(self::promo(), self::SALIDA, null));
        $this->assertTrue(R::vale(self::promo(["hasta" => "2026-12-10"]), self::SALIDA, null));
        $this->assertTrue(R::vale(self::promo(["desde" => "2026-12-10"]), self::SALIDA, null));
        $this->assertFalse(R::vale(self::promo(["hasta" => "2026-12-09"]), self::SALIDA, null));
        $this->assertFalse(R::vale(self::promo(["desde" => "2026-12-11"]), self::SALIDA, null));
        $this->assertFalse(R::vale(self::promo(["activa" => false]), self::SALIDA, null));
    }

    public function testEmpresaRutaYClaseSiSeFijan(): void
    {
        $this->assertTrue(R::vale(self::promo(["empresa" => 1, "ruta" => 5, "busClase" => 2]), self::SALIDA, null));
        $this->assertFalse(R::vale(self::promo(["empresa" => 9]), self::SALIDA, null));
        $this->assertFalse(R::vale(self::promo(["ruta" => 9]), self::SALIDA, null));
        $this->assertFalse(R::vale(self::promo(["busClase" => 9]), self::SALIDA, null));
        // La salida sin clase de bus no entra en una promoción de una clase.
        $this->assertFalse(R::vale(self::promo(["busClase" => 2]), [...self::SALIDA, "busClase" => null], null));
    }

    public function testConCodigoSoloSiSeEscribe(): void
    {
        $promo = self::promo(["codigo" => "NAVIDAD"]);

        $this->assertFalse(R::vale($promo, self::SALIDA, null));
        $this->assertFalse(R::vale($promo, self::SALIDA, "OTRO"));
        $this->assertTrue(R::vale($promo, self::SALIDA, " navidad "));
        // La que no pide código vale igual aunque se escriba uno.
        $this->assertTrue(R::vale(self::promo(), self::SALIDA, "NAVIDAD"));
    }

    public function testGanaElMayorSinSumar(): void
    {
        $nino = ["id" => 3, "nombre" => "Niño", "porciento" => 50];
        $diciembre = ["id" => 7, "nombre" => "Diciembre", "porciento" => 20];
        $grande = ["id" => 8, "nombre" => "Aniversario", "porciento" => 60];

        $this->assertNull(R::elegir(null, []));
        $this->assertEquals(new Descuento(50, "Niño 50 %", 3), R::elegir($nino, [$diciembre]));
        $this->assertEquals(new Descuento(20, "Diciembre 20 %", null, 7), R::elegir(null, [$diciembre]));
        $this->assertEquals(new Descuento(60, "Aniversario 60 %", 3, 8), R::elegir($nino, [$diciembre, $grande]));
        // Empatados, el tipo de pasajero.
        $this->assertEquals(new Descuento(50, "Niño 50 %", 3), R::elegir($nino, [["id" => 9, "nombre" => "Otra", "porciento" => 50]]));
    }

    public function testImporteRedondeadoAlCentavo(): void
    {
        $q = static fn(int $centavos) => new Money($centavos, new Currency("GTQ"));

        $this->assertEquals($q(5000), R::importe($q(10000), 50));
        // 33 % de Q 125.55 = 41.4315 → 41.43.
        $this->assertEquals($q(4143), R::importe($q(12555), 33));
        // 50 % de Q 0.25 = 12.5 centavos → 13 (la mitad, a favor del pasajero).
        $this->assertEquals($q(13), R::importe($q(25), 50));
        $this->assertEquals($q(10000), R::importe($q(10000), 100));
        $this->assertEquals($q(0), R::importe($q(0), 30));
    }

    public function testElCodigoSeNormaliza(): void
    {
        $this->assertSame("NAVIDAD26", R::codigo(" navidad26 "));
        $this->assertNull(R::codigo("  "));
        $this->assertNull(R::codigo(null));
    }
}
