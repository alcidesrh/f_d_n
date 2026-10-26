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

    public function testLaFacturaCubreLosBoletosVivosDeLaVenta(): void
    {
        foreach ([EstadoFacturacion::CERTIFICADA, EstadoFacturacion::PENDIENTE] as $estado) {
            $venta = self::venta($estado);
            $b1 = self::boleto(1, $venta, 1);
            $b2 = self::boleto(2, $venta, 2);
            $b3 = self::boleto(3, $venta, 3);
            $this->assertSame([$b1, $b2, $b3], ReglasBoletos::boletosDeLaFactura($venta), $estado->value);

            // Lo ya anulado o reasignado no cuenta como vivo.
            $b2->setEstado(EstadoBoletoAsiento::ANULADO);
            $b3->setEstado(EstadoBoletoAsiento::REASIGNADO);
            $this->assertSame([$b1], ReglasBoletos::boletosDeLaFactura($venta), $estado->value);
        }
    }

    public function testUnaVentaSinFacturaNoCubreBoletos(): void
    {
        foreach ([EstadoFacturacion::NO_APLICA, EstadoFacturacion::ANULADA] as $estado) {
            $venta = self::venta($estado);
            self::boleto(1, $venta, 1);
            self::boleto(2, $venta, 2);

            $this->assertSame([], ReglasBoletos::boletosDeLaFactura($venta), $estado->value);
        }
    }

    public function testLoQueQuedaDeUnaFacturaConjuntaSeFacturaSinLoAnulado(): void
    {
        $venta = self::venta(EstadoFacturacion::CERTIFICADA)->setTotal(Money::GTQ(45000));
        self::boleto(1, $venta, 1, 10000);
        $b2 = self::boleto(2, $venta, 2, 15000);
        $b3 = self::boleto(3, $venta, 3, 20000);
        $this->assertEquals(Money::GTQ(45000), ReglasBoletos::totalFacturable($venta));

        $b2->setEstado(EstadoBoletoAsiento::ANULADO);
        // El reasignado sigue cobrado: su reemplazo está en la misma venta.
        $b3->setEstado(EstadoBoletoAsiento::REASIGNADO);
        $this->assertEquals(Money::GTQ(30000), ReglasBoletos::totalFacturable($venta));
    }

    public function testSoloSeReasignaEntreSalidasDeLaMismaEmpresa(): void
    {
        $a = self::salida(1, "2026-10-11 08:00", 1);
        $b = self::salida(2, "2026-10-11 09:00", 2);

        $this->assertSame("empresa_distinta", $this->codigo(fn() => ReglasBoletos::exigirEmpresaCompatible($a, $b)));
        $this->assertNull($this->codigo(fn() => ReglasBoletos::exigirEmpresaCompatible($a, self::salida(3, "2026-10-12 08:00", 1))));
    }

    public function testUnPospuestoSeOperaAunqueSuSalidaYaHayaPasado(): void
    {
        $venta = self::venta(EstadoFacturacion::CERTIFICADA);
        $b = self::boleto(1, $venta, 1);
        $b->setEstado(EstadoBoletoAsiento::POSPUESTO);
        $despues = new \DateTimeImmutable("2030-01-01 00:00");

        $this->assertNull($this->codigo(fn() => ReglasBoletos::exigirOperable($b, $despues, "anular")));
        $emitido = self::boleto(2, $venta, 2)->setSalida(self::salida(1, "2026-10-11 08:00"));
        $this->assertSame("salida_iniciada", $this->codigo(fn() => ReglasBoletos::exigirOperable($emitido, $despues, "anular")));
    }

    public function testElNuevoAsientoDebeCostarLoMismo(): void
    {
        $b = self::boleto(1, self::venta(EstadoFacturacion::CERTIFICADA), 1, 27000);

        $this->assertNull($this->codigo(fn() => ReglasBoletos::exigirMismoPrecio($b, Money::GTQ(27000), 5)));
        $this->assertSame("precio_distinto", $this->codigo(fn() => ReglasBoletos::exigirMismoPrecio($b, Money::GTQ(25000), 5)));
        $this->assertSame("precio_distinto", $this->codigo(fn() => ReglasBoletos::exigirMismoPrecio($b, Money::USD(27000), 5)), "otra moneda");
    }

    public function testFueraDeHoraConAutorizacionMientrasLaSalidaNoSeCierre(): void
    {
        $ahora = new \DateTimeImmutable("2026-10-10 12:00");
        $boleto = function (EstadoSalida $estado, string $fecha = "2026-10-10 08:00"): BoletoAsiento {
            $b = self::boleto(1, self::venta(EstadoFacturacion::CERTIFICADA));
            $b->setSalida(self::salida(1, $fecha, 1, $estado));

            return $b;
        };

        // Ya salió: solo la hora lo impide.
        $this->assertTrue(ReglasBoletos::soloPorHora($boleto(EstadoSalida::PROGRAMADA), $ahora));
        $this->assertTrue(ReglasBoletos::soloPorHora($boleto(EstadoSalida::INICIADA), $ahora));
        $this->assertNull($this->codigo(fn() => ReglasBoletos::exigirOperableFueraDeHora($boleto(EstadoSalida::INICIADA), "anular")));
        // A tiempo no hace falta; con la salida finalizada, ni con autorización.
        $this->assertFalse(ReglasBoletos::soloPorHora($boleto(EstadoSalida::PROGRAMADA, "2026-10-10 18:00"), $ahora));
        $finalizada = $boleto(EstadoSalida::INICIADA);
        $finalizada->getSalida()->setEstado(EstadoSalida::FINALIZADA);
        $this->assertFalse(ReglasBoletos::soloPorHora($finalizada, $ahora));
        $this->assertSame("salida_no_operable", $this->codigo(fn() => ReglasBoletos::exigirOperableFueraDeHora($finalizada, "anular")));
        // Un boleto que no está emitido tampoco.
        $chequeado = $boleto(EstadoSalida::INICIADA);
        $chequeado->setEstado(EstadoBoletoAsiento::CHEQUEADO);
        $this->assertFalse(ReglasBoletos::soloPorHora($chequeado, $ahora));
    }
}
