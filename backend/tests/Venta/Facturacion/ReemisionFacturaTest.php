<?php

declare(strict_types=1);

namespace App\Tests\Venta\Facturacion;

use App\Entity\Asiento;
use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Entity\Enclave;
use App\Entity\Enum\AsientoClase;
use App\Entity\Factura;
use App\Entity\Salida;
use App\Entity\Trayecto;
use App\Venta\Boleto\DatosBoleto;
use App\Venta\Facturacion\CertificacionFallida;
use App\Venta\Facturacion\CertificadorSimulado;
use App\Venta\Facturacion\Facturador;
use Doctrine\ORM\EntityManagerInterface;
use Money\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

/**
 * Lo que dejó una compra modificada en la página (ADR-059) sin factura y
 * con el boleto mal impreso: la factura de una reemisión necesita otro
 * código interno, y una venta puede tener asientos en varias salidas.
 */
final class ReemisionFacturaTest extends TestCase
{
    public function testCadaFacturaDeLaVentaLlevaOtroCodigoInterno(): void
    {
        $venta = self::venta();
        $this->assertSame("FDN-VENTA-7", Facturador::referencia($venta));

        $venta->contarFacturaAnulada();
        $this->assertSame("FDN-VENTA-7-R1", Facturador::referencia($venta));
        $venta->contarFacturaAnulada();
        $this->assertSame("FDN-VENTA-7-R2", Facturador::referencia($venta));
    }

    public function testAnularCuentaCadaFacturaUnaVez(): void
    {
        $facturador = $this->facturador();
        $venta = self::venta();
        $this->assertNull($facturador->anular($venta, "Sin factura"));
        $this->assertSame(0, $venta->getFacturasAnuladas());

        $venta->certificar(self::factura());
        $facturador->anular($venta, "Modificación de la compra");
        $this->assertTrue($venta->getFactura()?->isAnulada());
        $this->assertSame(1, $venta->getFacturasAnuladas());

        // Reintentar una anulación ya hecha no vuelve a contarla (ni a llamar al certificador).
        $facturador->anular($venta, "Modificación de la compra");
        $this->assertSame(1, $venta->getFacturasAnuladas());
    }

    public function testSiElCertificadorNoLaAnulaElCodigoNoCambia(): void
    {
        $venta = self::venta()->certificar(self::factura());
        try {
            $this->facturador(falla: true)->anular($venta, "Modificación de la compra");
            $this->fail("El certificador simulado debía fallar.");
        } catch (CertificacionFallida) {
        }

        $this->assertFalse($venta->getFactura()?->isAnulada());
        $this->assertSame("FDN-VENTA-7", Facturador::referencia($venta));
    }

    public function testElComprobanteSeparaLosViajesDeLaVenta(): void
    {
        // Compra de dos asientos en la salida 1; cada uno pasó a otra salida, dos días después.
        $trayecto = (new Trayecto())->setOrigen(self::enclave(1, "Guatemala"))->setDestino(self::enclave(2, "Flores"));
        $trayecto->setId(23);
        $tarde = self::salida(3543, "2026-11-16 14:00");
        $manana = self::salida(3316, "2026-11-16 06:00");
        $venta = self::venta()->setTotal(Money::GTQ(55000));
        self::boleto($venta, $tarde, $trayecto, 11, 25000);
        self::boleto($venta, $manana, $trayecto, 58, 30000);

        $b = DatosBoleto::de($venta);

        $this->assertCount(2, $b["viajes"]);
        // Por hora de salida, cada uno con su asiento.
        $this->assertSame([3316, 3543], array_map(static fn(array $v) => $v["salida"]["id"], $b["viajes"]));
        $this->assertSame([[58], [11]], array_map(static fn(array $v) => array_column($v["boletos"], "asiento"), $b["viajes"]));
        $this->assertSame("2026-11-16T06:00:00+00:00", $b["viajes"][0]["salida"]["salidaOrigen"]);
        // Lo de siempre: el primer viaje arriba y todos los asientos de la venta.
        $this->assertSame(3316, $b["salida"]["id"]);
        $this->assertSame("Flores", $b["destino"]["nombre"]);
        $this->assertSame([11, 58], array_column($b["boletos"], "asiento"));
        $this->assertSame("Q 550.00", $b["total"]["texto"]);
    }

    public function testUnaVentaDeUnaSalidaEsUnSoloViaje(): void
    {
        $trayecto = (new Trayecto())->setOrigen(self::enclave(1, "Guatemala"))->setDestino(self::enclave(2, "Flores"));
        $trayecto->setId(23);
        $salida = self::salida(3316, "2026-11-16 06:00");
        $venta = self::venta()->setTotal(Money::GTQ(50000));
        self::boleto($venta, $salida, $trayecto, 12, 25000);
        self::boleto($venta, $salida, $trayecto, 11, 25000);

        $viajes = DatosBoleto::de($venta)["viajes"];

        $this->assertCount(1, $viajes);
        $this->assertSame([11, 12], array_column($viajes[0]["boletos"], "asiento"));
    }

    private function facturador(bool $falla = false): Facturador
    {
        return new Facturador(
            new CertificadorSimulado($falla),
            $this->createStub(EntityManagerInterface::class),
            new MockClock("2026-11-14 10:00:00"),
        );
    }

    private static function venta(): BoletoVenta
    {
        $v = new BoletoVenta();
        $v->setId(7);

        return $v;
    }

    private static function factura(): Factura
    {
        return (new Factura())
            ->setUuid(Uuid::v4())
            ->setEmisorNit("1234567")
            ->setReceptopNit("CF")
            ->setFecha(new \DateTime("2026-11-14 09:00:00"));
    }

    private static function enclave(int $id, string $nombre): Enclave
    {
        $e = (new Enclave())->setNombre($nombre);
        $e->setId($id);

        return $e;
    }

    private static function salida(int $id, string $fecha): Salida
    {
        $s = (new Salida())->setFecha(new \DateTime($fecha, new \DateTimeZone("UTC")));
        $s->setId($id);

        return $s;
    }

    private static function boleto(BoletoVenta $venta, Salida $salida, Trayecto $trayecto, int $asiento, int $centavos): void
    {
        $b = (new BoletoAsiento())
            ->setAsiento((new Asiento())->setNumero($asiento)->setClase(AsientoClase::A))
            ->setSalida($salida)
            ->setTrayecto($trayecto)
            ->setPrecio(Money::GTQ($centavos));
        $b->setId($asiento);
        $venta->addAsiento($b);
    }
}
