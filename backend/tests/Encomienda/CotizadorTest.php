<?php

declare(strict_types=1);

namespace App\Tests\Encomienda;

use App\Encomienda\Cotizador;
use Money\Money;
use PHPUnit\Framework\TestCase;

/**
 * Precio de una encomienda (ADR-035). Los casos con número son encomiendas
 * reales del legado: el cálculo debe dar lo mismo que cobró.
 */
final class CotizadorTest extends TestCase
{
    private static function q(int $quetzales): Money
    {
        return Money::GTQ($quetzales * 100);
    }

    /** @return array{tarifa: Money, recargo: Money, cantidad: int} */
    private static function articulo(int $tarifa, int $recargo, int $cantidad): array
    {
        return ["tarifa" => self::q($tarifa), "recargo" => self::q($recargo), "cantidad" => $cantidad];
    }

    public function testUnPaqueteSinDescuento(): void
    {
        // 10645186: 51 lb (Q45) de Santa Elena a Guatemala (recargo Q5).
        $c = Cotizador::cotizar([self::articulo(45, 5, 1)]);

        $this->assertEquals(self::q(50), $c->total);
        $this->assertEquals(self::q(0), $c->descuento);
        $this->assertSame(0, $c->porcentajeDescuento);
    }

    public function testDescuentoDeCincoPorCientoRedondeadoAQuetzal(): void
    {
        // 10645124: 3 paquetes de 1 lb (Q30, sin recargo) → 90 − 4.50 ≈ 5 = 85.
        $c = Cotizador::cotizar([self::articulo(30, 0, 3)]);
        $this->assertEquals(self::q(85), $c->total);
        $this->assertEquals(self::q(5), $c->descuento);

        // 10634900: 4 × (30 + 5) = 140 − 7 = 133.
        $this->assertEquals(self::q(133), Cotizador::cotizar([self::articulo(30, 5, 4)])->total);
    }

    public function testEspecialesPorCantidad(): void
    {
        // 10634936: 4 × Q95 = 380 − 19 = 361; 10634935: 3 × Q40 = 120 − 6 = 114.
        $this->assertEquals(self::q(361), Cotizador::cotizar([self::articulo(95, 0, 4)])->total);
        $this->assertEquals(self::q(114), Cotizador::cotizar([self::articulo(40, 0, 3)])->total);
        // 10635006: 2 × Q80, sin descuento.
        $this->assertEquals(self::q(160), Cotizador::cotizar([self::articulo(80, 0, 2)])->total);
    }

    public function testElDescuentoCuentaLosBultosDeTodaLaEncomienda(): void
    {
        $c = Cotizador::cotizar([self::articulo(30, 0, 2), self::articulo(50, 0, 1)]);

        $this->assertSame(3, $c->bultos());
        $this->assertEquals(self::q(110), $c->subtotal);
        $this->assertEquals(self::q(6), $c->descuento); // 5.50 → 6
        $this->assertEquals(self::q(104), $c->total);
        $this->assertEquals(self::q(60), $c->lineas[0]["importe"]);
    }

    public function testEscalaDeDescuento(): void
    {
        $this->assertSame(0, Cotizador::porcentajeDescuento(2));
        $this->assertSame(5, Cotizador::porcentajeDescuento(3));
        $this->assertSame(5, Cotizador::porcentajeDescuento(5));
        $this->assertSame(7, Cotizador::porcentajeDescuento(6));
        $this->assertSame(7, Cotizador::porcentajeDescuento(9));
        $this->assertSame(10, Cotizador::porcentajeDescuento(10));
        $this->assertSame(10, Cotizador::porcentajeDescuento(100));
    }

    public function testTarifasConCentavos(): void
    {
        // 2 × 12.50 = 25.00, sin descuento: no se redondea el total.
        $c = Cotizador::cotizar([["tarifa" => Money::GTQ(1250), "recargo" => Money::GTQ(0), "cantidad" => 2]]);

        $this->assertEquals(Money::GTQ(2500), $c->total);
    }
}
