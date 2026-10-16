<?php

namespace App\Tests\Entity;

use App\Entity\BoletoTarifa;
use Money\Money;
use PHPUnit\Framework\TestCase;

final class BoletoTarifaPrecioTest extends TestCase
{
    public function testSinPrecioNoTieneImporteNiMonto(): void
    {
        $tarifa = new BoletoTarifa();
        $this->assertNull($tarifa->getImporte());
        $this->assertNull($tarifa->getPrecioMonto());
    }

    public function testElMontoSeGuardaEnCentavosYEnQuetzales(): void
    {
        $tarifa = (new BoletoTarifa())->setPrecioMonto(125.5);
        $this->assertTrue($tarifa->getPrecio()->equals(Money::GTQ(12550)));
        $this->assertSame(125.5, $tarifa->getPrecioMonto());
        $this->assertSame('Q 125.50', $tarifa->getImporte());
    }

    public function testRedondeaAlCentavoYConservaLaMoneda(): void
    {
        $tarifa = (new BoletoTarifa())->setPrecio(Money::USD(100))->setPrecioMonto(19.999);
        $this->assertTrue($tarifa->getPrecio()->equals(Money::USD(2000)));
        $this->assertSame('USD 20.00', $tarifa->getImporte());
    }

    public function testImporteConMiles(): void
    {
        $this->assertSame('Q 1,250.00', (new BoletoTarifa())->setPrecio(Money::GTQ(125000))->getImporte());
    }
}
