<?php

declare(strict_types=1);

namespace App\Tests\Sincronizacion;

use App\Sincronizacion\Nuevo\FacturaParaLegado;
use PHPUnit\Framework\TestCase;

final class FacturaParaLegadoTest extends TestCase
{
    private const DTE = "0f1e2d3c-4b5a-6978-8796-a5b4c3d2e1f0";
    private const OTRO = "11111111-2222-3333-4444-555555555555";

    /** @return array{fg: ?int, uuid: ?string, importe: int, anulada: bool} */
    private static function fila(?int $fg = null, ?string $uuid = null, int $importe = 0, bool $anulada = false): array
    {
        return ["fg" => $fg, "uuid" => $uuid, "importe" => $importe, "anulada" => $anulada];
    }

    public function testVentaDeDosAsientosTotalEnElPrimeroCeroEnElOtroYTicket(): void
    {
        $plan = FacturaParaLegado::calcular([502, 501], [501 => self::fila(), 502 => self::fila()], ["uuid" => strtoupper(self::DTE), "total" => 50000], [], null);

        $this->assertSame([501 => 50000, 502 => 0], $plan->crear);
        $this->assertSame([], $plan->importes);
        $this->assertSame([], $plan->anular);
        $this->assertSame(["id" => null, "identificador" => 501, "boletos" => [501, 502]], $plan->ticket);
    }

    public function testUnSoloBoletoSinTicket(): void
    {
        $plan = FacturaParaLegado::calcular([7], [7 => self::fila()], ["uuid" => self::DTE, "total" => 25000], [], null);

        $this->assertSame([7 => 25000], $plan->crear);
        $this->assertNull($plan->ticket);
    }

    public function testYaEscritaNoQuedaNada(): void
    {
        $alla = [501 => self::fila(1, self::DTE, 50000), 502 => self::fila(2, self::DTE, 0)];
        $ticket = ["id" => 9, "identificador" => 501, "boletos" => [502, 501]];

        $this->assertTrue(FacturaParaLegado::calcular([501, 502], $alla, ["uuid" => self::DTE, "total" => 50000], [], $ticket)->vacio());
    }

    public function testLaDelErrorAnteriorTotalRepetidoSeCorrige(): void
    {
        // Cada boleto con su fila por el total de la venta: así las escribía la sincronización.
        $alla = [501 => self::fila(1, self::DTE, 50000), 502 => self::fila(2, self::DTE, 50000)];
        $plan = FacturaParaLegado::calcular([501, 502], $alla, ["uuid" => self::DTE, "total" => 50000], [], null);

        $this->assertSame([], $plan->crear);
        $this->assertSame([2 => 0], $plan->importes);
        $this->assertSame(["id" => null, "identificador" => 501, "boletos" => [501, 502]], $plan->ticket);
    }

    public function testAnulacionTotalAnulaLasFilasYNoTocaElTicket(): void
    {
        $alla = [501 => self::fila(1, self::DTE, 50000), 502 => self::fila(2, self::DTE, 0)];
        $plan = FacturaParaLegado::calcular([], $alla, null, [self::DTE => "2026-11-12 10:00:00"], ["id" => 9, "identificador" => 501, "boletos" => [501, 502]]);

        $this->assertSame([1 => "2026-11-12 10:00:00", 2 => "2026-11-12 10:00:00"], $plan->anular);
        $this->assertSame([], $plan->crear);
        $this->assertNull($plan->ticket);
    }

    public function testAnulacionParcialAnulaElDteViejoYFacturaLoQueQueda(): void
    {
        // Tres de 250; se anula el 501. Aquí: DTE viejo anulado y otro por 500 para 502 y 503.
        $alla = [
            501 => self::fila(1, self::DTE, 75000),
            502 => self::fila(2, self::DTE, 0),
            503 => self::fila(3, self::DTE, 0),
        ];
        $plan = FacturaParaLegado::calcular(
            [502, 503],
            $alla,
            ["uuid" => self::OTRO, "total" => 50000],
            [self::DTE => "2026-11-12 10:00:00"],
            ["id" => 9, "identificador" => 501, "boletos" => [501, 502, 503]],
        );

        $this->assertSame([1 => "2026-11-12 10:00:00", 2 => "2026-11-12 10:00:00", 3 => "2026-11-12 10:00:00"], $plan->anular);
        $this->assertSame([502 => 50000, 503 => 0], $plan->crear);
        $this->assertSame(["id" => 9, "identificador" => 502, "boletos" => [502, 503]], $plan->ticket);
    }

    public function testRefacturaPendienteSoloAnula(): void
    {
        // La factura nueva aún no se certifica: la venta queda sin factura vigente.
        $alla = [501 => self::fila(1, self::DTE, 50000), 502 => self::fila(2, self::DTE, 0)];
        $plan = FacturaParaLegado::calcular([502], $alla, null, [self::DTE => "2026-11-12 10:00:00"], null);

        $this->assertCount(2, $plan->anular);
        $this->assertSame([], $plan->crear);
    }

    public function testReasignarElPortadorPasaElTotalAlQueQueda(): void
    {
        // 501 (portador) se reasignó al 600 en la misma venta; la factura sigue.
        $alla = [
            501 => self::fila(1, self::DTE, 50000),
            502 => self::fila(2, self::DTE, 0),
            600 => self::fila(),
        ];
        $plan = FacturaParaLegado::calcular([502, 600], $alla, ["uuid" => self::DTE, "total" => 50000], [], ["id" => 9, "identificador" => 501, "boletos" => [501, 502]]);

        $this->assertSame([600 => 0], $plan->crear);
        $this->assertSame([1 => 0, 2 => 50000], $plan->importes);
        $this->assertSame(["id" => 9, "identificador" => 502, "boletos" => [502, 600]], $plan->ticket);
    }

    public function testReasignarOtroQueNoEsElPortador(): void
    {
        $alla = [
            501 => self::fila(1, self::DTE, 50000),
            502 => self::fila(2, self::DTE, 0),
            600 => self::fila(),
        ];
        $plan = FacturaParaLegado::calcular([501, 600], $alla, ["uuid" => self::DTE, "total" => 50000], [], ["id" => 9, "identificador" => 501, "boletos" => [501, 502]]);

        $this->assertSame([600 => 0], $plan->crear);
        $this->assertSame([], $plan->importes);
        $this->assertSame(["id" => 9, "identificador" => 501, "boletos" => [501, 600]], $plan->ticket);
    }

    public function testBoletoQueNoLlegoAlLegadoNoCuenta(): void
    {
        $plan = FacturaParaLegado::calcular([501, 502], [502 => self::fila()], ["uuid" => self::DTE, "total" => 50000], [], null);

        $this->assertSame([502 => 50000], $plan->crear);
        $this->assertNull($plan->ticket);
    }

    public function testUuidNormalizado(): void
    {
        $this->assertSame(self::DTE, FacturaParaLegado::uuid("  " . strtoupper(self::DTE) . " "));
        $this->assertNull(FacturaParaLegado::uuid("no es un uuid"));
        $this->assertNull(FacturaParaLegado::uuid(null));
    }

    public function testBoletosDelTicketComoLosGuardaDoctrine(): void
    {
        $guardado = FacturaParaLegado::serializarBoletos([501, 502]);

        $this->assertSame('a:2:{i:0;s:3:"501";i:1;s:3:"502";}', $guardado);
        $this->assertSame([501, 502], FacturaParaLegado::boletosDeTicket($guardado));
        $this->assertSame([501, 502], FacturaParaLegado::boletosDeTicket("a:2:{i:0;i:501;i:1;i:502;}"));
        $this->assertSame([], FacturaParaLegado::boletosDeTicket("basura"));
        $this->assertSame([], FacturaParaLegado::boletosDeTicket('O:8:"stdClass":0:{}'));
    }
}
