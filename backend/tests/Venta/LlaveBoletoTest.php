<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Venta\Boleto\LlaveBoleto;
use PHPUnit\Framework\TestCase;

/** Código de abordaje del boleto (ADR-063). */
final class LlaveBoletoTest extends TestCase
{
    public function testElCodigoVuelveASuBoleto(): void
    {
        $llaves = new LlaveBoleto("secreto");
        $codigo = $llaves->codigo(1234567);

        $this->assertMatchesRegularExpression('/^FDN1\.1234567\.[A-Za-z0-9_-]{12}$/', $codigo);
        $this->assertSame(1234567, $llaves->boleto($codigo));
        $this->assertSame(1234567, $llaves->boleto(" {$codigo}\n"));
    }

    public function testUnCodigoAjenoOAlteradoNoVale(): void
    {
        $llaves = new LlaveBoleto("secreto");
        $codigo = $llaves->codigo(42);

        $this->assertNull($llaves->boleto(str_replace(".42.", ".43.", $codigo)), "la firma de otro boleto");
        $this->assertNull((new LlaveBoleto("otro"))->boleto($codigo), "otra llave");
        $this->assertNull($llaves->boleto("FDN1.42."));
        $this->assertNull($llaves->boleto("00000042"));
        $this->assertNull($llaves->boleto(""));
    }

    public function testElCodigoDeBarrasEsElIdDeLaVenta(): void
    {
        $this->assertSame(42, LlaveBoleto::venta("00000042"));
        $this->assertSame(42, LlaveBoleto::venta(" 42 "));
        $this->assertNull(LlaveBoleto::venta("00000000"));
        $this->assertNull(LlaveBoleto::venta("FDN1.42.abc"));
        $this->assertNull(LlaveBoleto::venta("4 2"));
    }
}
