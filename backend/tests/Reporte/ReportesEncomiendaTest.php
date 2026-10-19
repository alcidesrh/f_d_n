<?php

declare(strict_types=1);

namespace App\Tests\Reporte;

use App\Reporte\Encomienda\CuadreEncomiendas;
use App\Reporte\Encomienda\DetalleFacturaEncomiendas;
use App\Reporte\Encomienda\DetalleGeneralEncomiendas;
use App\Reporte\Encomienda\FiltroEncomiendas;
use App\Reporte\Encomienda\LineaEncomienda;
use App\Reporte\Encomienda\ManifiestoEncomiendas;
use App\Reporte\Encomienda\PendientesEntregaEncomiendas;
use App\Reporte\ReporteRechazado;
use PHPUnit\Framework\TestCase;

/** Cálculo puro de los reportes de encomiendas (ADR-035). */
final class ReportesEncomiendaTest extends TestCase
{
    /** @param array<string, mixed> $c */
    private static function linea(array $c = []): LineaEncomienda
    {
        $d = [
            "id" => 1, "creada" => "2026-10-19 08:00", "origen" => "Guatemala", "destino" => "Flores", "estado" => "recibida",
            "modalidad" => "pagada", "total" => 3500, "dte" => 100, "usuario" => "ana", "bultos" => 1, "salidaId" => null,
            "entregadaEn" => null, "entregadaPor" => null, "estadoFacturacion" => "certificada",
        ];
        $c = [...$d, ...$c];

        return new LineaEncomienda(
            $c["id"], sprintf("%08d", $c["id"]), new \DateTimeImmutable($c["creada"]), "Pionera", $c["origen"], $c["destino"], "SEP",
            $c["estado"], $c["modalidad"], $c["total"], "GTQ", null, $c["dte"] === null ? null : "ABC", $c["dte"], false, null,
            $c["estadoFacturacion"], $c["usuario"], "Guatemala", "Rem", "Des", null, null, "Paquete 5 lb", $c["bultos"], "Paquete",
            $c["salidaId"], $c["salidaId"] === null ? null : new \DateTimeImmutable("2026-10-19 15:30"), $c["salidaId"] === null ? null : "65",
            ["JUAN", "N/D"], "Guatemala - Flores", "Guatemala - Flores",
            $c["entregadaEn"] === null ? null : new \DateTimeImmutable($c["entregadaEn"]), $c["entregadaPor"], null, null, null, null,
        );
    }

    public function testElCuadreSumaPorUsuarioLoFacturadoLoCobradoYLasInternas(): void
    {
        $recibidas = [
            self::linea(["id" => 1, "total" => 2000]),
            self::linea(["id" => 2, "modalidad" => "por_cobrar", "total" => 7500, "dte" => null]),
            self::linea(["id" => 3, "modalidad" => "interna", "total" => 0, "dte" => null, "usuario" => "luis"]),
            // Anulada: no suma (va en su sección).
            self::linea(["id" => 4, "estado" => "anulada", "total" => 9900]),
            self::linea(["id" => 5, "estado" => "embarcada", "total" => 1000, "salidaId" => 77]),
        ];
        $cobradas = [self::linea(["id" => 9, "modalidad" => "por_cobrar", "estado" => "entregada", "total" => 4000, "entregadaEn" => "2026-10-19 11:00", "entregadaPor" => "luis"])];

        $c = new CuadreEncomiendas(new \DateTimeImmutable("2026-10-19"), "Guatemala", null, $recibidas, $cobradas, [$recibidas[4]], [$recibidas[3]], ["ana" => "Ana Pérez"]);

        $this->assertSame(["ana", "luis"], array_column($c->usuarios, "usuario"));
        $this->assertSame("Ana Pérez", $c->usuarios[0]["nombre"]);
        $this->assertSame(3000, $c->usuarios[0]["facturado"]);
        $this->assertSame(2, $c->usuarios[0]["facturadas"]);
        $this->assertSame(7500, $c->usuarios[0]["porCobrar"]);
        $this->assertSame(4000, $c->usuarios[1]["cobrado"]);
        $this->assertSame(1, $c->usuarios[1]["internas"]);
        $this->assertSame(7000, $c->ingreso());
        // Lo que sigue en bodega de lo recibido ese día.
        $this->assertSame([1, 2, 3], array_map(static fn(LineaEncomienda $l) => $l->id, $c->pendientesEnvio));
        $this->assertSame(77, $c->salidas[0]["salidaId"]);
        $this->assertSame(1000, $c->totalSalidas["pagado"]);
    }

    public function testElDetalleDeFacturaAgrupaPorDiaDeCobroYDestino(): void
    {
        $d = new DetalleFacturaEncomiendas("18/10/2026 al 19/10/2026", null, null, [
            self::linea(["id" => 1, "creada" => "2026-10-19 09:00", "destino" => "Poptún", "total" => 3000]),
            self::linea(["id" => 2, "creada" => "2026-10-19 08:00", "total" => 2000]),
            // Por cobrar registrada el 10, cobrada el 18: cuenta el día del cobro.
            self::linea(["id" => 3, "creada" => "2026-10-10 08:00", "modalidad" => "por_cobrar", "estado" => "entregada", "entregadaEn" => "2026-10-18 17:00", "entregadaPor" => "luis", "total" => 4000]),
        ]);

        $this->assertSame(["2026-10-18", "2026-10-19"], array_map(static fn(array $g) => $g["dia"]->format("Y-m-d"), $d->grupos));
        $this->assertSame(["Flores", "Poptún"], array_column($d->grupos[1]["destinos"], "destino"));
        $this->assertSame(9000, $d->total);
        $this->assertSame("luis", DetalleFacturaEncomiendas::cobrador($d->grupos[0]["destinos"][0]["filas"][0]));
        $this->assertSame(["cantidad" => 3, "total" => 9000, "pagadas" => 2, "porCobrar" => 1, "pendientes" => 0], $d->resumen());
    }

    public function testElDetalleGeneralNoSumaLasAnuladas(): void
    {
        $d = new DetalleGeneralEncomiendas("19/10/2026", null, null, null, [
            self::linea(["id" => 1, "total" => 2000]),
            self::linea(["id" => 2, "modalidad" => "por_cobrar", "total" => 7500]),
            self::linea(["id" => 3, "estado" => "anulada", "total" => 3500]),
        ]);

        $this->assertSame(2000, $d->totales["pagado"]);
        $this->assertSame(7500, $d->totales["porCobrar"]);
        $this->assertSame(1, $d->resumen()["anuladas"]);
    }

    public function testPendientesYManifiesto(): void
    {
        $lineas = [self::linea(["id" => 1, "creada" => "2026-10-12 10:00"]), self::linea(["id" => 2, "destino" => "Poptún", "modalidad" => "por_cobrar", "total" => 5000])];
        $p = new PendientesEntregaEncomiendas(new \DateTimeImmutable("2026-10-19 09:00"), "Flores", null, $lineas);
        $this->assertSame(7, $p->masAntigua);
        $this->assertCount(2, $p->grupos);
        $this->assertSame(5000, $p->totales["porCobrar"]);

        $m = new ManifiestoEncomiendas(77, new \DateTimeImmutable("2026-10-19 15:30"), "Guatemala - Flores", "Pionera", "65", ["JUAN", "N/D"], null, $lineas);
        $this->assertSame(["Flores", "Poptún"], array_column($m->grupos, "destino"));
        $this->assertSame(8500, $m->totales["total"]);
    }

    public function testElFiltroValidaElRango(): void
    {
        $f = FiltroEncomiendas::desdeQuery(["desde" => "2026-10-01", "estacion" => "4", "soloPorCobrar" => "1"]);
        $this->assertEquals(new \DateTimeImmutable("2026-10-02"), $f->fin());
        $this->assertTrue($f->soloPorCobrar);
        $this->assertSame(9, $f->conAlcance(9, null)->estacionId);

        $this->expectException(ReporteRechazado::class);
        FiltroEncomiendas::desdeQuery(["desde" => "2026-01-01", "hasta" => "2026-10-01"]);
    }
}
