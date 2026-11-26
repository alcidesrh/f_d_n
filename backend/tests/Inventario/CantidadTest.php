<?php

declare(strict_types=1);

namespace App\Tests\Inventario;

use App\Inventario\Cantidad;
use PHPUnit\Framework\TestCase;

/** Cantidades y costos del inventario en enteros (ADR-071). */
final class CantidadTest extends TestCase
{
    public function testLeeLoQueEscribeElUsuarioYLoQueGuardaLaBase(): void
    {
        self::assertSame(1250, Cantidad::centesimas("12.5"));
        self::assertSame(1250, Cantidad::centesimas("12.50"));
        self::assertSame(1200, Cantidad::centesimas(12));
        self::assertSame(250, Cantidad::centesimas(2.5));
        self::assertSame(-500, Cantidad::centesimas("-5.00"));
        self::assertSame(120000, Cantidad::centesimas("1,200"));
        self::assertSame(1000, Cantidad::centesimas(" 10. "));
        self::assertSame(35000, Cantidad::diezmilesimas("3.5"));
        self::assertSame(12345, Cantidad::diezmilesimas("1.2345"));
    }

    public function testRechazaLoQueNoEsUnNumeroODemasiadosDecimales(): void
    {
        self::assertNull(Cantidad::centesimas("1.234"));
        self::assertNull(Cantidad::centesimas("abc"));
        self::assertNull(Cantidad::centesimas(""));
        self::assertNull(Cantidad::centesimas(null));
        self::assertNull(Cantidad::centesimas("1e3"));
        self::assertNull(Cantidad::diezmilesimas("1.23456"));
    }

    public function testEscribeConSusDecimales(): void
    {
        self::assertSame("12.50", Cantidad::texto(1250));
        self::assertSame("0.05", Cantidad::texto(5));
        self::assertSame("-3.00", Cantidad::texto(-300));
        self::assertSame("3.5000", Cantidad::costoTexto(35000));
        self::assertSame("0.0001", Cantidad::costoTexto(1));
    }

    public function testElPromedioPonderaLoQueHabiaYLoQueEntra(): void
    {
        // 10 a Q2.00 y entran 30 a Q4.00: (20 + 120) / 40 = Q3.50.
        self::assertSame(35000, Cantidad::promedio(1000, 20000, 3000, 40000));
        // Sin nada antes (o en negativo por un ajuste), vale lo que entra.
        self::assertSame(40000, Cantidad::promedio(0, 20000, 3000, 40000));
        self::assertSame(40000, Cantidad::promedio(-100, 20000, 3000, 40000));
        // Sin entrar nada, queda como estaba.
        self::assertSame(20000, Cantidad::promedio(1000, 20000, 0, 40000));
        // 3 a Q1.00 y 1 a Q2.00 = Q1.25; al mismo costo, queda igual.
        self::assertSame(12500, Cantidad::promedio(300, 10000, 100, 20000));
        self::assertSame(10000, Cantidad::promedio(100, 10000, 200, 10000));
    }

    public function testElValorRedondeaAlCentavo(): void
    {
        // 12.5 rollos a Q3.3333 = Q41.66625 → Q41.67.
        self::assertSame(4167, Cantidad::valorCentavos(1250, 33333));
        self::assertSame(0, Cantidad::valorCentavos(0, 33333));
        self::assertSame(-4167, Cantidad::valorCentavos(-1250, 33333));
    }
}
