<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Entity\BoletoVenta;
use App\Entity\Enum\AsientoClase;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoFacturacion;
use App\Venta\Boleto\Listado\FiltroBoletos;
use App\Venta\Boleto\Listado\ListadoBoletos;
use App\Venta\Excepcion\VentaRechazada;
use PHPUnit\Framework\TestCase;

/** Filtros del listado de boletos y el tipo de documento de cada venta. */
final class FiltroBoletosTest extends TestCase
{
    private static function hoy(): \DateTimeImmutable
    {
        return new \DateTimeImmutable("2026-10-11 15:30");
    }

    public function testSinFechasSonTreintaDiasAntesYDespues(): void
    {
        $f = FiltroBoletos::desdeQuery([], self::hoy());

        $this->assertSame("2026-09-11", $f->desde->format("Y-m-d"));
        $this->assertSame("2026-11-10", $f->hasta->format("Y-m-d"));
        $this->assertSame("2026-11-11 00:00", $f->finExclusivo()->format("Y-m-d H:i"));
        $this->assertSame(1, $f->pagina);
        $this->assertSame(15, $f->porPagina);
        $this->assertSame("id", $f->orden);
        $this->assertTrue($f->descendente);
    }

    public function testLeeLosFiltros(): void
    {
        $f = FiltroBoletos::desdeQuery([
            "desde" => "2026-10-01",
            "hasta" => "2026-10-31",
            "id" => " 42 ",
            "estado" => "emitido,chequeado,emitido",
            "clase" => "b",
            "documento" => "voucher",
            "origen" => "  Quetzal ",
            "serie" => "",
            "pagina" => "0",
            "porPagina" => "33",
            "orden" => "fecha",
            "dir" => "asc",
        ], self::hoy());

        $this->assertSame(42, $f->id);
        $this->assertSame([EstadoBoletoAsiento::EMITIDO, EstadoBoletoAsiento::CHEQUEADO], $f->estados);
        $this->assertSame(AsientoClase::B, $f->clase);
        $this->assertSame("voucher", $f->documento);
        $this->assertSame("Quetzal", $f->origen);
        $this->assertNull($f->serieFactura);
        $this->assertSame(1, $f->pagina);
        // Un tamaño que no se ofrece vuelve al de siempre.
        $this->assertSame(15, $f->porPagina);
        $this->assertSame("fecha", $f->orden);
        $this->assertFalse($f->descendente);
    }

    public function testRechazaLoInvalido(): void
    {
        $casos = [
            "rango_invalido" => ["desde" => "2026-10-10", "hasta" => "2026-10-01"],
            "fecha_invalida" => ["desde" => "10/10/2026"],
            "parametro_invalido" => ["id" => "12a"],
        ];
        foreach ($casos as $codigo => $q) {
            $this->assertSame($codigo, $this->codigo($q), json_encode($q));
        }
        foreach ([["estado" => "perdido"], ["clase" => "C"], ["documento" => "recibo"], ["orden" => "precio"]] as $q) {
            $this->assertSame("parametro_invalido", $this->codigo($q), json_encode($q));
        }
        // Seis meses como mucho.
        $this->assertSame("rango_invalido", $this->codigo(["desde" => "2026-01-01", "hasta" => "2026-07-07"]));
        $this->assertNull($this->codigo(["desde" => "2026-01-01", "hasta" => "2026-07-05"]));
    }

    public function testTipoDeDocumento(): void
    {
        $venta = static fn(bool $voucher = false, bool $cortesia = false, EstadoFacturacion $fact = EstadoFacturacion::NO_APLICA) => (new BoletoVenta())
            ->setVoucher($voucher)
            ->setCortesia($cortesia)
            ->setEstadoFacturacion($fact);

        $this->assertSame("voucher", ListadoBoletos::documento($venta(voucher: true, cortesia: true)));
        $this->assertSame("cortesia", ListadoBoletos::documento($venta(cortesia: true)));
        $this->assertSame("pendiente", ListadoBoletos::documento($venta(fact: EstadoFacturacion::PENDIENTE)));
        $this->assertSame("sin_factura", ListadoBoletos::documento($venta()));
    }

    /** @param array<string, string> $q */
    private function codigo(array $q): ?string
    {
        try {
            FiltroBoletos::desdeQuery($q, self::hoy());
        } catch (VentaRechazada $e) {
            return $e->codigo;
        }

        return null;
    }
}
