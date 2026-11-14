<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Venta\EnLinea\Modificacion\LlaveCliente;
use App\Venta\EnLinea\Modificacion\Penalizacion;
use App\Venta\EnLinea\Modificacion\PoliticaModificacion;
use App\Venta\EnLinea\Modificacion\ReglasModificacion;
use App\Venta\EnLinea\Modificacion\SolicitudModificacion;
use App\Venta\Excepcion\VentaRechazada;
use Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** Reglas puras de «Mis compras»: llave sin contraseña, política, cuentas y pedido de modificación. */
final class ModificacionCompraTest extends TestCase
{
    public function testLlaveDevuelveElCorreoNormalizado(): void
    {
        $llaves = new LlaveCliente("secreto", new MockClock("2026-11-13 10:00:00"));

        $this->assertSame("ana@ejemplo.com", $llaves->correo($llaves->emitir("  Ana@Ejemplo.com ")));
    }

    public function testLlaveAlteradaOConOtroSecretoNoEntra(): void
    {
        $reloj = new MockClock("2026-11-13 10:00:00");
        $llave = (new LlaveCliente("secreto", $reloj))->emitir("ana@ejemplo.com");
        [$carga, $firma] = explode(".", $llave);
        $otra = rtrim(strtr(base64_encode("v1|9999999999|otro@ejemplo.com"), "+/", "-_"), "=");

        foreach ([null, "", "abc", "{$otra}.{$firma}", "{$carga}.AAAA"] as $mala) {
            $this->assertSame("sin_llave", $this->rechazo(fn() => (new LlaveCliente("secreto", $reloj))->correo($mala))->codigo);
        }
        $this->assertSame("sin_llave", $this->rechazo(fn() => (new LlaveCliente("otro secreto", $reloj))->correo($llave))->codigo);
    }

    public function testLlaveVence(): void
    {
        $reloj = new MockClock("2026-11-13 10:00:00");
        $llaves = new LlaveCliente("secreto", $reloj);
        $llave = $llaves->emitir("ana@ejemplo.com", 1);

        $reloj->modify("+23 hours");
        $this->assertSame("ana@ejemplo.com", $llaves->correo($llave));
        $reloj->modify("+2 hours");
        $e = $this->rechazo(fn() => $llaves->correo($llave));
        $this->assertSame("llave_vencida", $e->codigo);
        $this->assertSame(401, $e->estadoHttp);
    }

    public function testSeModificaHastaLosMinutosConfiguradosAntesDeSalir(): void
    {
        // El ejemplo de la regla: mínimo 30 minutos y sale a las 8:30 → hasta las 8:00.
        $politica = new PoliticaModificacion(2, 30, Penalizacion::ninguna());
        $salida = new \DateTimeImmutable("2026-11-20 08:30:00");

        $this->assertEquals(new \DateTimeImmutable("2026-11-20 08:00:00"), $politica->limite($salida));
        $this->assertNull($politica->motivoCerrada($salida, new \DateTimeImmutable("2026-11-20 07:59:59")));
        $this->assertNotNull($politica->motivoCerrada($salida, new \DateTimeImmutable("2026-11-20 08:00:00")));
        $this->assertNotNull($politica->motivoCerrada($salida, new \DateTimeImmutable("2026-11-20 09:00:00")));
        $this->assertSame("modificacion_cerrada", $this->rechazo(fn() => $politica->exigirAbierta($salida, new \DateTimeImmutable("2026-11-20 08:10:00")))->codigo);
    }

    public function testTopeDeCambiosDeUnMismoAsiento(): void
    {
        $politica = new PoliticaModificacion(2, 30, Penalizacion::ninguna());

        $this->assertSame(2, $politica->restantes(0));
        $this->assertSame(0, $politica->restantes(2));
        $politica->exigirRestantes(1, 12);
        $this->assertSame("modificaciones_agotadas", $this->rechazo(fn() => $politica->exigirRestantes(2, 12))->codigo);
    }

    public function testConCeroModificacionesElClienteNoModifica(): void
    {
        $politica = new PoliticaModificacion(0, 30, Penalizacion::ninguna());

        $this->assertNotNull($politica->motivoCerrada(new \DateTimeImmutable("2026-11-20 08:30:00"), new \DateTimeImmutable("2026-11-01 08:00:00")));
    }

    public function testPenalizacionPorModificacionOPorAsiento(): void
    {
        $this->assertSame("0", Penalizacion::ninguna()->para(3)->getAmount());
        $this->assertSame("2550", Penalizacion::de("25,5")->para(3)->getAmount(), "una vez por modificación");
        $this->assertSame("7650", Penalizacion::de("25.50", true)->para(3)->getAmount(), "por cada asiento cambiado");
        $this->assertSame("0", Penalizacion::de(25, true)->para(0)->getAmount(), "solo agregar asientos no se penaliza");
        $this->assertSame("25.00", Penalizacion::de(25)->quetzales);
    }

    public function testPenalizacionInvalida(): void
    {
        foreach (["-1", "abc", (string) (Penalizacion::MAXIMO_QUETZALES + 1)] as $mala) {
            $this->assertSame("penalizacion_invalida", $this->rechazo(fn() => Penalizacion::de($mala))->codigo);
        }
    }

    public static function cambios(): iterable
    {
        yield "más caro: paga la diferencia" => [10000, 15000, 15000, 5000, 0];
        yield "mismo precio" => [10000, 10000, 10000, 0, 0];
        yield "más barato: conserva lo pagado, sin reembolso" => [15000, 10000, 15000, 0, 5000];
    }

    #[DataProvider("cambios")]
    public function testDiferenciaAsientoPorAsiento(int $pagado, int $nuevo, int $precio, int $diferencia, int $sinReembolso): void
    {
        $c = ReglasModificacion::cambio(Money::GTQ($pagado), Money::GTQ($nuevo));

        $this->assertSame((string) $precio, $c["precio"]->getAmount());
        $this->assertSame((string) $diferencia, $c["diferencia"]->getAmount());
        $this->assertSame((string) $sinReembolso, $c["sinReembolso"]->getAmount());
    }

    public function testTotalSumaDiferenciasNuevosYPenalizacion(): void
    {
        $total = ReglasModificacion::total([Money::GTQ(5000), Money::GTQ(0)], [Money::GTQ(12000)], Money::GTQ(2500));

        $this->assertSame("19500", $total->getAmount());
    }

    public function testSolicitudDeModificacion(): void
    {
        $s = SolicitudModificacion::desdeArray([
            "cambios" => [["boleto" => 5, "salida" => "9", "trayecto" => 3, "asiento" => 40]],
            "nuevos" => [["salida" => 4, "asiento" => 41]],
        ]);

        $this->assertSame(["boleto" => 5, "salida" => 9, "trayecto" => 3, "asiento" => 40], $s->cambios[0]);
        $this->assertNull($s->nuevos[0]["trayecto"]);
        $this->assertSame([4, 9], $s->salidasDestino(), "se bloquean siempre en el mismo orden");
        $this->assertEquals($s, SolicitudModificacion::desdeArray($s->toArray()), "lo guardado se vuelve a leer igual");
    }

    public static function solicitudesInvalidas(): iterable
    {
        yield "vacía" => [[], "sin_cambio"];
        yield "sin asiento" => [["cambios" => [["boleto" => 1, "salida" => 2]]], "modificacion_invalida"];
        yield "id no entero" => [["nuevos" => [["salida" => "x", "asiento" => 2]]], "modificacion_invalida"];
        yield "el mismo boleto dos veces" => [["cambios" => [["boleto" => 1, "salida" => 2, "asiento" => 3], ["boleto" => 1, "salida" => 2, "asiento" => 4]]], "modificacion_invalida"];
        yield "el mismo asiento dos veces" => [["cambios" => [["boleto" => 1, "salida" => 2, "asiento" => 3]], "nuevos" => [["salida" => 2, "asiento" => 3]]], "modificacion_invalida"];
        yield "demasiados" => [["nuevos" => array_map(static fn(int $i) => ["salida" => 1, "asiento" => $i], range(1, SolicitudModificacion::MAX_ASIENTOS + 1))], "modificacion_invalida"];
    }

    #[DataProvider("solicitudesInvalidas")]
    public function testSolicitudInvalida(array $datos, string $codigo): void
    {
        $this->assertSame($codigo, $this->rechazo(fn() => SolicitudModificacion::desdeArray($datos))->codigo);
    }

    private function rechazo(callable $operacion): VentaRechazada
    {
        try {
            $operacion();
        } catch (VentaRechazada $e) {
            return $e;
        }
        $this->fail("Debió rechazarlo");
    }
}
