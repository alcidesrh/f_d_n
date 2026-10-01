<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Entity\Asiento;
use App\Entity\BoletoVenta;
use App\Entity\Enum\AsientoClase;
use App\Venta\Cotizacion;
use App\Venta\EnLinea\FiltroComprasWeb;
use App\Venta\EnLinea\Recargo;
use App\Venta\EnLinea\SolicitudCarrito;
use App\Venta\Excepcion\AsientosNoDisponibles;
use App\Venta\Excepcion\VentaRechazada;
use Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/** Reglas puras de la página web (ADR-023): recargo, carrito y filtro del dashboard. */
final class VentaEnLineaTest extends TestCase
{
    public static function recargos(): iterable
    {
        yield "sin recargo" => ["0", 25000, 25000];
        yield "10 %" => ["10", 25000, 27500];
        yield "redondea al centavo" => ["7.5", 33333, 35833];
        yield "coma decimal" => ["2,5", 10000, 10250];
        yield "cien por ciento" => [100, 12345, 24690];
    }

    #[DataProvider("recargos")]
    public function testRecargoSeSumaPorAsiento(string|int $porciento, int $centavos, int $esperado): void
    {
        $this->assertSame((string) $esperado, Recargo::de($porciento)->aplicar(Money::GTQ($centavos))->getAmount());
    }

    public function testRecargoNormalizaYValida(): void
    {
        $this->assertSame("7.50", Recargo::de("7.5")->porciento);
        $this->assertSame("0.00", Recargo::ninguno()->porciento);
        foreach (["-1", "100.01", "abc"] as $malo) {
            try {
                Recargo::de($malo);
                $this->fail("Debió rechazar {$malo}");
            } catch (VentaRechazada $e) {
                $this->assertSame("recargo_invalido", $e->codigo);
            }
        }
    }

    public function testCotizacionConRecargoRecalculaElTotal(): void
    {
        $a = (new Asiento())->setClase(AsientoClase::A);
        $b = (new Asiento())->setClase(AsientoClase::B);
        $c = new Cotizacion([
            ["asiento" => $a, "precio" => Money::GTQ(25000), "tarifaId" => 1],
            ["asiento" => $b, "precio" => Money::GTQ(32500), "tarifaId" => 2],
        ], Money::GTQ(57500));

        $con = $c->conRecargo(Recargo::de("10"));

        $this->assertSame("63250", $con->total->getAmount());
        $this->assertSame("35750", $con->precioDe($b)->getAmount());
        $this->assertSame("57500", $c->total->getAmount(), "la original no cambia");
    }

    public function testSolicitudCarritoIdaYVuelta(): void
    {
        $s = SolicitudCarrito::desdeArray([
            ["salida" => 9, "trayecto" => 3, "asientos" => [5, "6"]],
            ["salida" => 4, "asientos" => [7]],
        ]);

        $this->assertSame([9, 4], array_column($s->viajes, "salida"));
        $this->assertSame([5, 6], $s->viajes[0]["asientos"]);
        $this->assertNull($s->viajes[1]["trayecto"]);
        $this->assertSame([4, 9], $s->salidasParaBloquear(), "se bloquean siempre en el mismo orden");
    }

    public static function carritosInvalidos(): iterable
    {
        yield "vacío" => [[], "carrito_invalido"];
        yield "tres viajes" => [[["salida" => 1, "asientos" => [1]], ["salida" => 2, "asientos" => [1]], ["salida" => 3, "asientos" => [1]]], "carrito_invalido"];
        yield "sin asientos" => [[["salida" => 1, "asientos" => []]], "carrito_sin_asientos"];
        yield "repetidos" => [[["salida" => 1, "asientos" => [4, 4]]], "carrito_invalido"];
        yield "demasiados" => [[["salida" => 1, "asientos" => range(1, SolicitudCarrito::MAX_ASIENTOS + 1)]], "carrito_lleno"];
        yield "misma salida dos veces" => [[["salida" => 1, "asientos" => [1]], ["salida" => 1, "asientos" => [2]]], "carrito_invalido"];
        yield "sin salida" => [[["asientos" => [1]]], "carrito_invalido"];
    }

    #[DataProvider("carritosInvalidos")]
    public function testSolicitudCarritoInvalida(array $datos, string $codigo): void
    {
        try {
            SolicitudCarrito::desdeArray($datos);
            $this->fail("Debió rechazar el carrito");
        } catch (VentaRechazada $e) {
            $this->assertSame($codigo, $e->codigo);
        }
    }

    public function testAsientosOcupadosPorViaje(): void
    {
        $e = AsientosNoDisponibles::enViajes([
            ["viaje" => 0, "salida" => 9, "asientos" => [50, 51], "numeros" => [12, 3]],
            ["viaje" => 1, "salida" => 4, "asientos" => [70], "numeros" => [8]],
        ]);

        $this->assertSame(409, $e->estadoHttp);
        $this->assertStringContainsString("ida: 3, 12; regreso: 8", $e->getMessage());
        $this->assertSame([50, 51], $e->toArray()["viajes"][0]["asientos"]);
    }

    public function testTokenDelRegresoEsEstableYDistinto(): void
    {
        $t = Uuid::fromString("729b0e60-bc02-48e3-91db-64a043ae68bf");

        $this->assertTrue(BoletoVenta::tokenRegreso($t)->equals(BoletoVenta::tokenRegreso($t)));
        $this->assertFalse(BoletoVenta::tokenRegreso($t)->equals($t));
    }

    public function testFiltroPorDefectoCompletadasRecientes(): void
    {
        $f = FiltroComprasWeb::desdeQuery([]);

        $this->assertSame(["completado"], $f->estados);
        $this->assertSame("creado", $f->orden);
        $this->assertTrue($f->descendente);
        $this->assertSame(1, $f->pagina);
    }

    public function testFiltroIgnoraLoQueNoEntiende(): void
    {
        $f = FiltroComprasWeb::desdeQuery([
            "estado" => "completado,rechazado,inventado",
            "creadoDesde" => "2026-10-01",
            "creadoHasta" => "01/10/2026",
            "empresa" => "7",
            "origen" => "x",
            "idaVuelta" => "si",
            "montoMinimo" => "125.5",
            "orden" => "DROP TABLE",
            "porPagina" => "5000",
            "q" => "  ana  ",
        ]);

        $this->assertSame(["rechazado", "completado"], array_values(array_intersect(["rechazado", "completado"], $f->estados)));
        $this->assertCount(2, $f->estados);
        $this->assertSame("2026-10-01", $f->creadoDesde);
        $this->assertNull($f->creadoHasta);
        $this->assertSame(7, $f->empresa);
        $this->assertNull($f->origen);
        $this->assertTrue($f->idaVuelta);
        $this->assertSame(12550, $f->montoMinimo);
        $this->assertSame("creado", $f->orden);
        $this->assertSame(FiltroComprasWeb::MAX_POR_PAGINA, $f->porPagina);
        $this->assertSame("ana", $f->texto);
    }

    public function testFiltroEstadoVacioSonTodos(): void
    {
        $this->assertSame([], FiltroComprasWeb::desdeQuery(["estado" => ""])->estados);
    }
}
