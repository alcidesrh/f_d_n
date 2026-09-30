<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Entity\Agencia;
use App\Venta\Agencia\SaldoAgencia;
use App\Venta\Comprador;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\Facturacion\Facturador;
use App\Venta\Pago\Tarjeta;
use App\Venta\SolicitudVenta;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidacionesVentaTest extends TestCase
{
    public static function nits(): iterable
    {
        yield "cliente del ticket" => ["28119266", true];
        yield "empresa con guion" => ["4397700-6", true];
        yield "certificador" => ["4150686", true];
        yield "verificador errado" => ["28119267", false];
        yield "letras" => ["ABC", false];
    }

    #[DataProvider("nits")]
    public function testNitGuatemalteco(string $nit, bool $valido): void
    {
        $this->assertSame($valido, Comprador::nitValido($nit));
    }

    public function testNormalizarNit(): void
    {
        $this->assertSame("CF", Facturador::normalizarNit(null));
        $this->assertSame("CF", Facturador::normalizarNit(" c/f "));
        $this->assertSame("43977006", Facturador::normalizarNit("4397700-6"));
    }

    public function testCompradorExigeCorreo(): void
    {
        $this->expectException(VentaRechazada::class);
        Comprador::desdeArray(["nombre" => "Ana", "email" => "no-es-correo"]);
    }

    public function testCompradorSinNitEsConsumidorFinal(): void
    {
        $this->assertSame("CF", Comprador::desdeArray(["nombre" => "Ana", "email" => "ana@example.com"])->nit);
    }

    public function testTarjetaValida(): void
    {
        $t = Tarjeta::desdeArray(["numero" => "4242 4242 4242 4242", "expira" => "12/30", "cvv" => "123", "titular" => "Ana"], new \DateTimeImmutable("2026-09-29"));

        $this->assertSame("4242", $t->ultimos4());
        $this->assertSame("visa", $t->marcaTarjeta());
        $this->assertStringNotContainsString("4242424242424242", print_r($t, true), "no se filtra el número completo");
    }

    public static function tarjetasInvalidas(): iterable
    {
        yield "luhn" => [["numero" => "4242424242424241", "expira" => "12/30", "cvv" => "123", "titular" => "A"]];
        yield "amex" => [["numero" => "378282246310005", "expira" => "12/30", "cvv" => "1234", "titular" => "A"]];
        yield "vencida" => [["numero" => "5555555555554444", "expira" => "08/26", "cvv" => "123", "titular" => "A"]];
        yield "cvv" => [["numero" => "5555555555554444", "expira" => "12/30", "cvv" => "1", "titular" => "A"]];
    }

    #[DataProvider("tarjetasInvalidas")]
    public function testTarjetaInvalida(array $datos): void
    {
        $this->expectException(VentaRechazada::class);
        Tarjeta::desdeArray($datos, new \DateTimeImmutable("2026-09-29"));
    }

    public function testBonificacionRedondeaHaciaAbajo(): void
    {
        $this->assertSame(500, SaldoAgencia::bonificacion(10000, "5.00"));
        $this->assertSame(333, SaldoAgencia::bonificacion(10001, "3.33"));
        $this->assertSame(0, SaldoAgencia::bonificacion(10000, null));
    }

    public function testElSaldoDeUnaAgenciaNuncaQuedaNegativo(): void
    {
        $agencia = new Agencia();
        $agencia->aplicarMovimiento(1000);

        $this->expectException(\DomainException::class);
        $agencia->aplicarMovimiento(-1001);
    }

    public function testSolicitudVentaDesdeArray(): void
    {
        $s = SolicitudVenta::desdeArray([
            "token" => "1B4E28BA-2FA1-41D2-883F-0016D3CCA427",
            "salida" => "/api/salidas/5",
            "asientos" => [3, ["asiento" => 4, "cliente" => 9]],
            "cliente" => 7,
            "observacion" => "  viaja con mascota ",
        ]);

        $this->assertSame("1b4e28ba-2fa1-41d2-883f-0016d3cca427", $s->token);
        $this->assertSame(5, $s->salidaId);
        $this->assertNull($s->trayectoId);
        $this->assertSame([["asiento" => 3, "cliente" => null], ["asiento" => 4, "cliente" => 9]], $s->asientos);
        $this->assertSame("viaja con mascota", $s->observacion);
    }

    public function testSolicitudSinAsientos(): void
    {
        $this->expectException(VentaRechazada::class);
        SolicitudVenta::desdeArray(["token" => "1b4e28ba-2fa1-41d2-883f-0016d3cca427", "salida" => 1, "cliente" => 1, "asientos" => []]);
    }
}
