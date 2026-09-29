<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Venta\Boleto\Code128;
use PHPUnit\Framework\TestCase;

final class Code128Test extends TestCase
{
    public function testCadaPatronOcupaOnceModulosYLaParadaTrece(): void
    {
        $this->assertCount(107, Code128::PATRONES);
        foreach (Code128::PATRONES as $i => $p) {
            $this->assertSame($i === 106 ? 13 : 11, array_sum(str_split($p)), "patrón {$i}");
        }
    }

    public function testDigitosParesVanEnSubconjuntoC(): void
    {
        // 105 + 12·1 + 34·2 = 185 ≡ 82 (mod 103)
        $this->assertSame([105, 12, 34, 82, 106], Code128::simbolos("1234"));
    }

    public function testTextoVaEnSubconjuntoB(): void
    {
        // 104 + 33·1 ('A' = 65 − 32) = 137 ≡ 34
        $this->assertSame([104, 33, 34, 106], Code128::simbolos("A"));
    }

    public function testSvgEmpiezaYTerminaConMargen(): void
    {
        $modulos = Code128::modulos("00000042");
        $this->assertFalse($modulos[0]);
        $this->assertTrue($modulos[10], "la primera barra del inicio");
        $this->assertFalse(end($modulos));
        $this->assertStringStartsWith("<svg", Code128::svg("00000042"));
    }
}
