<?php

declare(strict_types=1);

namespace App\Tests\Venta\Facturacion;

use App\Entity\ConfiguracionForcon;
use App\Venta\Facturacion\EmisorEnLinea;
use App\Venta\Facturacion\Forcon\AjustesForcon;
use PHPUnit\Framework\TestCase;

final class EmisorEnLineaTest extends TestCase
{
    public function testEmisorPorNitSinImportarElFormato(): void
    {
        $regla = self::regla(["2033683-7" => "6907303-1"]);

        self::assertSame("69073031", $regla->para("2033683-7"));
        self::assertSame("69073031", $regla->para(" 20336837 "));
    }

    public function testSinReglaFacturaLaMismaEmpresa(): void
    {
        $regla = self::regla(["2033683-7" => "6907303-1"]);

        self::assertNull($regla->para("4397700-6"));
        self::assertNull($regla->para(null));
        self::assertNull((new EmisorEnLinea())->para("2033683-7"));
    }

    /** @param array<string, string> $emisores */
    private static function regla(array $emisores): EmisorEnLinea
    {
        return new EmisorEnLinea(AjustesForcon::fijos((new ConfiguracionForcon())->setEmisoresEnLinea($emisores)));
    }
}
