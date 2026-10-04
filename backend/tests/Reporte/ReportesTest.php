<?php

declare(strict_types=1);

namespace App\Tests\Reporte;

use App\Reporte\CuadreVentaBoletos;
use App\Reporte\DetalleFacturaBoletos;
use App\Reporte\Dinero;
use App\Reporte\FiltroCuadre;
use App\Reporte\FiltroDetalle;
use App\Reporte\LineaBoleto;
use App\Reporte\ReporteRechazado;
use App\Reporte\Xlsx\LibroXlsx;
use PHPUnit\Framework\TestCase;

/** Reglas puras de los reportes de venta: filtros, cuadre, detalle y el escritor de Excel. */
final class ReportesTest extends TestCase
{
    public static function linea(
        int $boleto,
        int $venta,
        string $usuario = "amejia",
        int $centavos = 8500,
        string $vendida = "2026-10-03 05:30:18",
        string $salida = "2026-10-03 06:30",
        bool $anulado = false,
        bool $sinCobro = false,
        bool $tarjeta = false,
        ?int $dte = 123,
        int $salidaId = 1,
    ): LineaBoleto {
        return new LineaBoleto(
            $boleto, $venta, new \DateTimeImmutable($vendida), $usuario, "Nombre {$usuario}", $anulado, $centavos, "GTQ", $sinCobro,
            $salidaId, new \DateTimeImmutable($salida), "61", "P088", "Guatemala - Quetzaltenango", "Guatemala", "Quetzaltenango", $boleto,
            $dte === null ? null : 10 + $venta, $dte === null ? null : "AB12", $dte, $dte === null ? "pendiente" : "certificada",
            $tarjeta, $tarjeta ? "012384" : null, null,
        );
    }

    public function testCuadreSumaPorUsuarioYRestaLoAnulado(): void
    {
        $c = new CuadreVentaBoletos(new \DateTimeImmutable("2026-10-03"), "Guatemala", "PIONERA", "GTQ", [
            self::linea(1, 1, "amejia", 8500),
            self::linea(2, 1, "amejia", 8500),
            self::linea(3, 2, "amejia", 25000, anulado: true),
            self::linea(4, 3, "amarvin", 25000),
            self::linea(5, 4, "amarvin", 10000, sinCobro: true),
        ]);

        $this->assertSame(["amarvin", "amejia"], array_column($c->usuarios, "usuario"));
        $this->assertSame(
            ["usuario" => "amejia", "nombre" => "Nombre amejia", "ventas" => 2, "boletos" => 3, "recibido" => 42000, "anulado" => 25000, "facturado" => 17000],
            $c->usuarios[1],
        );
        $this->assertSame(25000, $c->usuarios[0]["recibido"], "el voucher o cortesía no suma");
        $this->assertSame(["ventas" => 4, "boletos" => 5, "recibido" => 67000, "anulado" => 25000, "facturado" => 42000], $c->totalUsuarios);
        $this->assertSame(25000, $c->totalAnulados);
        $this->assertSame(3, $c->anulados[0]["boletoId"]);
    }

    public function testCuadreSeparaSalidasDelDiaPrepagadosYTarjetas(): void
    {
        $c = new CuadreVentaBoletos(new \DateTimeImmutable("2026-10-03"), null, null, "GTQ", [
            self::linea(1, 1, salida: "2026-10-03 18:00", salidaId: 2),
            self::linea(2, 1, salida: "2026-10-03 06:30", salidaId: 1),
            self::linea(3, 2, salida: "2026-10-05 21:00", salidaId: 3, centavos: 145000),
            self::linea(4, 3, tarjeta: true, dte: null),
            self::linea(5, 3, tarjeta: true, dte: null),
            self::linea(6, 4, salida: "2026-10-03 06:30", salidaId: 1, anulado: true),
        ], [self::linea(9, 8, salida: "2026-10-03 09:00", salidaId: 4, centavos: 30000)]);

        $this->assertSame([1, 2], array_column($c->salidas, "salidaId"), "ordenadas por hora de salida");
        $this->assertSame(["06:30", "18:00"], array_column($c->salidas, "hora"));
        $this->assertSame(34000, $c->totalSalidas, "el anulado y el prepagado no cuentan en la venta por salida");
        $this->assertSame([3], array_column($c->prepagados, "salidaId"));
        $this->assertSame(145000, $c->totalPrepagados);
        $this->assertSame(30000, $c->totalPrepagadosOtras);
        $this->assertCount(1, $c->tarjetas, "una fila por venta, no por boleto");
        $this->assertSame(17000, $c->tarjetas[0]["venta"]);
        $this->assertSame("012384", $c->tarjetas[0]["tarjeta"]);
        $this->assertSame("—", $c->tarjetas[0]["factura"]);
    }

    public function testDetalleTotalizaPorMonedaYDescribeLaFactura(): void
    {
        $sin = self::linea(1, 1, dte: null);
        $con = self::linea(2, 2, vendida: "2026-10-03 04:00:00", tarjeta: true);
        $d = new DetalleFacturaBoletos("03/10/2026", null, null, [$sin, $con, self::linea(3, 3, sinCobro: true)]);

        $this->assertSame([2, 1, 3], array_map(static fn(LineaBoleto $l) => $l->boletoId, $d->filas), "por fecha de venta");
        $this->assertSame(["GTQ" => ["cantidad" => 3, "total" => 17000]], $d->totales);
        $this->assertSame("Pendiente", DetalleFacturaBoletos::estadoDte($sin));
        $this->assertSame("123", DetalleFacturaBoletos::estadoDte($con));
        $this->assertSame("Aut. 012384", DetalleFacturaBoletos::descripcion($con));
        $this->assertSame(1, $d->resumen()["sinFactura"]);
    }

    public function testFiltrosValidanFechasYRango(): void
    {
        $f = FiltroDetalle::desdeQuery(["desde" => "2026-10-03", "autorizacion" => " 12 ", "soloTarjetas" => "1"]);
        $this->assertSame("03/10/2026", $f->rotulo());
        $this->assertSame("12", $f->autorizacion);
        $this->assertTrue($f->soloTarjetas);

        foreach ([["desde" => "03/10/2026"], ["desde" => "2026-02-31"], ["desde" => "2026-10-03", "hasta" => "2026-10-01"], ["desde" => "2026-01-01", "hasta" => "2026-12-31"]] as $q) {
            try {
                FiltroDetalle::desdeQuery($q);
                $this->fail("debió rechazar " . json_encode($q));
            } catch (ReporteRechazado $e) {
                $this->assertSame(400, $e->estadoHttp);
            }
        }

        $c = FiltroCuadre::desdeQuery(["fecha" => "2026-10-03", "moneda" => "gtq", "estacion" => "1"])->conAlcance(7, null);
        $this->assertSame([7, null, "GTQ"], [$c->estacionId, $c->empresaId, $c->moneda], "la estación del usuario manda");
        $this->expectException(ReporteRechazado::class);
        FiltroCuadre::desdeQuery(["fecha" => "2026-10-03"]);
    }

    public function testDineroUsaMilesYDosDecimales(): void
    {
        $this->assertSame("20,495.00", Dinero::numero(2049500));
        $this->assertSame("GTQ 0.50", Dinero::con(50, "GTQ"));
    }

    public function testLibroXlsxGeneraUnZipValidoConLosDatos(): void
    {
        $libro = (new LibroXlsx("Hoja & <1>"))->anchos([10, 12]);
        $libro->fila(["Encabezado", "Importe"], LibroXlsx::ENCABEZADO);
        $libro->fila(["a & b <c>", [12.5, LibroXlsx::DINERO]]);
        $libro->fila([[new \DateTimeImmutable("2026-10-03 12:00:00"), LibroXlsx::FECHA_HORA]]);
        $libro->congelar(2);
        $libro->autofiltro(1, 3, 2);

        $archivo = tempnam(sys_get_temp_dir(), "t");
        file_put_contents($archivo, $libro->contenido());
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($archivo));
        foreach (["[Content_Types].xml", "xl/workbook.xml", "xl/styles.xml", "xl/worksheets/sheet1.xml"] as $parte) {
            $xml = new \DOMDocument();
            $this->assertTrue($xml->loadXML((string) $zip->getFromName($parte)), "{$parte} es XML bien formado");
        }
        $hoja = (string) $zip->getFromName("xl/worksheets/sheet1.xml");
        $this->assertStringContainsString("a &amp; b &lt;c&gt;", $hoja);
        $this->assertStringContainsString("<v>12.5</v>", $hoja);
        $this->assertStringContainsString("<v>46298.5</v>", $hoja, "03/10/2026 12:00 como serie de Excel");
        $this->assertStringContainsString('ref="A1:B3"', $hoja);
        $this->assertStringContainsString('\'Hoja &amp; &lt;1&gt;\'!$A$1:$B$3', (string) $zip->getFromName("xl/workbook.xml"));
        $zip->close();
        unlink($archivo);
    }
}
