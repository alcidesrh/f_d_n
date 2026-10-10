<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Entity\Asiento;
use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Entity\Empresa;
use App\Entity\Enum\CanalVenta;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoFacturacion;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Salida;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\ReglasBoletos;
use Money\Money;
use PHPUnit\Framework\TestCase;

/** Reglas puras de anular y reasignar boletos. */
final class ReglasBoletosTest extends TestCase
{
    private static function salida(int $id, string $fecha, ?int $empresa = 1, EstadoSalida $estado = EstadoSalida::PROGRAMADA): Salida
    {
        $s = (new Salida())->setFecha(new \DateTime($fecha));
        $s->setId($id);
        if ($empresa !== null) {
            $e = new Empresa();
            $e->setId($empresa);
            $s->setEmpresa($e);
        }
        if ($estado !== EstadoSalida::PROGRAMADA) {
            $s->setEstado(EstadoSalida::ABORDANDO);
            if ($estado !== EstadoSalida::ABORDANDO) {
                $s->setEstado($estado);
            }
        }

        return $s;
    }

    private static function boleto(int $id, BoletoVenta $venta, int $asiento = 1, int $centavos = 10000): BoletoAsiento
    {
        $a = (new Asiento())->setNumero($asiento);
        $b = (new BoletoAsiento())->setAsiento($a)->setPrecio(Money::GTQ($centavos));
        $b->setId($id);
        $venta->addAsiento($b);

        return $b;
    }

    private static function venta(EstadoFacturacion $facturacion, CanalVenta $canal = CanalVenta::ESTACION): BoletoVenta
    {
        $v = (new BoletoVenta())->setEstadoFacturacion($facturacion)->setCanal($canal);
        $v->setId(7);

        return $v;
    }

    private function codigo(callable $f): ?string
    {
        try {
            $f();
        } catch (VentaRechazada $e) {
            return $e->codigo;
        }

        return null;
    }

    public function testSoloSeOperaAntesDeLaHoraDeSalida(): void
    {
        $ahora = new \DateTimeImmutable("2026-10-10 12:00");

        $this->assertNull($this->codigo(fn() => ReglasBoletos::exigirAntesDeSalir(self::salida(1, "2026-10-10 12:01"), $ahora, "anular")));
        $this->assertSame("salida_iniciada", $this->codigo(fn() => ReglasBoletos::exigirAntesDeSalir(self::salida(1, "2026-10-10 12:00"), $ahora, "anular")));
        $this->assertSame("salida_iniciada", $this->codigo(fn() => ReglasBoletos::exigirAntesDeSalir(self::salida(1, "2026-10-10 08:00"), $ahora, "anular")));
    }

    public function testNoSeOperaEnSalidasIniciadasNiCanceladas(): void
    {
        $ahora = new \DateTimeImmutable("2026-10-10 12:00");

        $this->assertNull($this->codigo(fn() => ReglasBoletos::exigirAntesDeSalir(self::salida(1, "2026-10-11 08:00", 1, EstadoSalida::ABORDANDO), $ahora, "anular")));
        $this->assertSame("salida_no_operable", $this->codigo(fn() => ReglasBoletos::exigirAntesDeSalir(self::salida(1, "2026-10-11 08:00", 1, EstadoSalida::INICIADA), $ahora, "anular")));
    }

    public function testSoloSeOperaSobreBoletosEmitidos(): void
    {
        $b = self::boleto(1, self::venta(EstadoFacturacion::NO_APLICA));
        $this->assertNull($this->codigo(fn() => ReglasBoletos::exigirEmitido($b)));

        $b->setEstado(EstadoBoletoAsiento::ANULADO);
        $this->assertSame("boleto_no_emitido", $this->codigo(fn() => ReglasBoletos::exigirEmitido($b)));
    }

    public function testUnaFacturaExigeAnularTodosLosBoletosVivosDeLaVenta(): void
    {
        $venta = self::venta(EstadoFacturacion::CERTIFICADA);
        $b1 = self::boleto(1, $venta, 1);
        $b2 = self::boleto(2, $venta, 2);

        $this->assertSame("venta_incompleta", $this->codigo(fn() => ReglasBoletos::exigirVentaCompleta($venta, [$b1])));
        $this->assertNull($this->codigo(fn() => ReglasBoletos::exigirVentaCompleta($venta, [$b1, $b2])));

        // Lo ya anulado o reasignado no cuenta como vivo.
        $b2->setEstado(EstadoBoletoAsiento::REASIGNADO);
        $this->assertNull($this->codigo(fn() => ReglasBoletos::exigirVentaCompleta($venta, [$b1])));
    }

    public function testElRechazoDiceQueBoletosFaltan(): void
    {
        $venta = self::venta(EstadoFacturacion::CERTIFICADA);
        $b1 = self::boleto(1, $venta, 1);
        self::boleto(2, $venta, 2);
        self::boleto(3, $venta, 3);

        try {
            ReglasBoletos::exigirVentaCompleta($venta, [$b1]);
            $this->fail("debía rechazar");
        } catch (VentaRechazada $e) {
            $this->assertSame([2, 3], $e->detalle["boletos"]);
            $this->assertSame(7, $e->detalle["venta"]);
            $this->assertStringContainsString("los asientos 2, 3", $e->getMessage());
        }
    }

    public function testVentasSinFacturaSePuedenAnularPorPartes(): void
    {
        foreach ([EstadoFacturacion::NO_APLICA, EstadoFacturacion::ANULADA] as $estado) {
            $venta = self::venta($estado);
            $b1 = self::boleto(1, $venta, 1);
            self::boleto(2, $venta, 2);

            $this->assertNull($this->codigo(fn() => ReglasBoletos::exigirVentaCompleta($venta, [$b1])), $estado->value);
        }
    }

    public function testUnaFacturaPorCertificarTambienCubreTodaLaVenta(): void
    {
        $venta = self::venta(EstadoFacturacion::PENDIENTE);
        $b1 = self::boleto(1, $venta, 1);
        self::boleto(2, $venta, 2);

        $this->assertSame("venta_incompleta", $this->codigo(fn() => ReglasBoletos::exigirVentaCompleta($venta, [$b1])));
    }

    public function testEntreEmpresasSoloSeReasignaLoVendidoEnLaWeb(): void
    {
        $a = self::salida(1, "2026-10-11 08:00", 1);
        $b = self::salida(2, "2026-10-11 09:00", 2);

        $this->assertSame("empresa_distinta", $this->codigo(fn() => ReglasBoletos::exigirEmpresaCompatible($a, $b, self::venta(EstadoFacturacion::CERTIFICADA, CanalVenta::ESTACION))));
        $this->assertSame("empresa_distinta", $this->codigo(fn() => ReglasBoletos::exigirEmpresaCompatible($a, $b, self::venta(EstadoFacturacion::NO_APLICA, CanalVenta::AGENCIA))));
        $this->assertNull($this->codigo(fn() => ReglasBoletos::exigirEmpresaCompatible($a, $b, self::venta(EstadoFacturacion::CERTIFICADA, CanalVenta::WEB))));
        $this->assertNull($this->codigo(fn() => ReglasBoletos::exigirEmpresaCompatible($a, self::salida(3, "2026-10-12 08:00", 1), self::venta(EstadoFacturacion::CERTIFICADA))));
    }

    public function testElNuevoAsientoDebeCostarLoMismo(): void
    {
        $b = self::boleto(1, self::venta(EstadoFacturacion::CERTIFICADA), 1, 27000);

        $this->assertNull($this->codigo(fn() => ReglasBoletos::exigirMismoPrecio($b, Money::GTQ(27000), 5)));
        $this->assertSame("precio_distinto", $this->codigo(fn() => ReglasBoletos::exigirMismoPrecio($b, Money::GTQ(25000), 5)));
        $this->assertSame("precio_distinto", $this->codigo(fn() => ReglasBoletos::exigirMismoPrecio($b, Money::USD(27000), 5)), "otra moneda");
    }
}
