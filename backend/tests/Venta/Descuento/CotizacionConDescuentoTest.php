<?php

declare(strict_types=1);

namespace App\Tests\Venta\Descuento;

use App\Entity\Asiento;
use App\Entity\Enum\AsientoClase;
use App\Venta\Cotizacion;
use App\Venta\Descuento\Descuento;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\TestCase;

/** La cotización con tipos de pasajero y promociones (ADR-066). */
final class CotizacionConDescuentoTest extends TestCase
{
    private static function q(int $centavos): Money
    {
        return new Money($centavos, new Currency("GTQ"));
    }

    private static function asiento(int $id): Asiento
    {
        $a = (new Asiento())->setNumero($id)->setClase(AsientoClase::A);
        $a->setId($id);

        return $a;
    }

    public function testCadaAsientoConSuDescuentoYElTotalLoQueSeCobra(): void
    {
        [$a, $b] = [self::asiento(1), self::asiento(2)];
        $c = (new Cotizacion([
            ["asiento" => $a, "precio" => self::q(10000), "tarifaId" => 5],
            ["asiento" => $b, "precio" => self::q(10000), "tarifaId" => 5],
        ], self::q(20000)))->conDescuentos([1 => new Descuento(50, "Niño 50 %", 3)]);

        $this->assertEquals(self::q(5000), $c->precioDe($a));
        $this->assertEquals(self::q(5000), $c->rebajaDe($a));
        $this->assertEquals(self::q(10000), $c->precioDe($b));
        $this->assertEquals(self::q(0), $c->rebajaDe($b));
        $this->assertEquals(self::q(15000), $c->total);
        $this->assertSame("Niño 50 %", $c->descuentoDe($a)?->motivo);
        $this->assertNull($c->descuentoDe($b));
    }

    public function testVolverACalcularParteDeLaTarifaNoDelPrecioYaRebajado(): void
    {
        $a = self::asiento(1);
        $c = (new Cotizacion([["asiento" => $a, "precio" => self::q(10000), "tarifaId" => 5]], self::q(10000)))
            ->conDescuentos([1 => new Descuento(50, "Niño 50 %", 3)])
            ->conDescuentos([1 => new Descuento(20, "Diciembre 20 %", null, 7)]);

        $this->assertEquals(self::q(8000), $c->precioDe($a));
        $this->assertEquals(self::q(8000), $c->total);
        // Y sin descuento, otra vez la tarifa.
        $this->assertEquals(self::q(10000), $c->conDescuentos([])->total);
    }

    public function testElJsonLlevaTarifaDescuentoYMotivo(): void
    {
        $a = self::asiento(1);
        $json = (new Cotizacion([["asiento" => $a, "precio" => self::q(10000), "tarifaId" => 5]], self::q(10000)))
            ->conDescuentos([1 => new Descuento(25, "Tercera edad 25 %", 4)])
            ->toArray();

        $this->assertSame(7500, $json["asientos"][0]["precio"]["centavos"]);
        $this->assertSame(10000, $json["asientos"][0]["lista"]["centavos"]);
        $this->assertSame(2500, $json["asientos"][0]["descuento"]["centavos"]);
        $this->assertSame("Tercera edad 25 %", $json["asientos"][0]["motivo"]);
        $this->assertSame(7500, $json["total"]["centavos"]);
    }
}
