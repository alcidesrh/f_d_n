<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Venta\Boleto\CodigoQr;
use PHPUnit\Framework\TestCase;

final class CodigoQrTest extends TestCase
{
    public function testLaVersionCreceConElTexto(): void
    {
        $this->assertSame(1, CodigoQr::version(14));
        $this->assertSame(2, CodigoQr::version(15));
        $this->assertSame(6, CodigoQr::version(106));
        $this->assertCount(21, CodigoQr::matriz("A"));
        $this->assertCount(25, CodigoQr::matriz("FDN1.1234567.AbCdEf012345"));
    }

    public function testLoQueNoCabeSeRechaza(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CodigoQr::version(107);
    }

    /** Datos de la versión 1-M: 16 codewords de datos y 10 de corrección. */
    public function testCodewordsDeLaVersionUno(): void
    {
        $codewords = CodigoQr::codewords("A", 1);

        $this->assertCount(26, $codewords);
        // Modo byte (0100), largo 1, 'A' (0x41), terminador y relleno 0xEC/0x11.
        $this->assertSame([0x40, 0x14, 0x10, 0xEC, 0x11, 0xEC], array_slice($codewords, 0, 6));
    }

    public function testBuscadoresSincroniaYModuloOscuro(): void
    {
        $m = CodigoQr::matriz("FDN1.42.abcdefABCDEF");
        $n = count($m);

        foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$x, $y]) {
            $this->assertTrue($m[$y][$x], "esquina del buscador");
            $this->assertFalse($m[$y + 1][$x + 1], "anillo claro");
            $this->assertTrue($m[$y + 3][$x + 3], "centro");
        }
        for ($i = 8; $i < $n - 8; $i++) {
            $this->assertSame($i % 2 === 0, $m[6][$i], "sincronía horizontal {$i}");
            $this->assertSame($i % 2 === 0, $m[$i][6], "sincronía vertical {$i}");
        }
        $this->assertTrue($m[$n - 8][8], "módulo oscuro");
    }

    public function testSvgLlevaMargenDeCuatroModulos(): void
    {
        $svg = CodigoQr::svg("A", 3);

        // 21 módulos + 4 de margen a cada lado, a 3 px.
        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 87 87" width="87" height="87"', $svg);
        $this->assertStringContainsString('<rect x="12" y="12" width="21" height="3"/>', $svg);
    }
}
