<?php

declare(strict_types=1);

namespace App\Tests\Tablero;

use App\Tablero\Periodo;
use App\Tablero\SalidaTablero;
use App\Tablero\Tablero;
use PHPUnit\Framework\TestCase;

/** Reglas puras del tablero de gestión: ventanas de comparación, ocupación, rutas y alertas. */
final class TableroTest extends TestCase
{
    private const AHORA = "2026-10-12 15:30";

    private static function salida(
        int $id,
        string $fecha,
        int $ocupado,
        int $asientos = 40,
        int $tramos = 1,
        string $estado = "finalizada",
        int $ruta = 1,
        int $ingresos = 0,
        int $boletos = 0,
        int $noPresentados = 0,
        ?int $bus = 1,
    ): SalidaTablero {
        return new SalidaTablero(
            $id, new \DateTimeImmutable($fecha), $estado, $ruta, "Ruta {$ruta}", $bus === null ? null : "B{$bus}", $bus, "PIONERA",
            $asientos, $tramos, null, $ocupado, $boletos, $ingresos, $noPresentados,
        );
    }

    /** @param list<SalidaTablero> $salidas */
    private static function tablero(
        int $dias = 7,
        array $ventas = [],
        array $salidas = [],
        array $sinCerrar = [],
        array $agencias = [],
        array $facturacion = ["pendientes" => 0, "conError" => 0, "masAntigua" => null],
        array $flota = ["buses" => 0, "vencidas" => [], "porVencer" => []],
        ?array $incidencias = null,
    ): Tablero {
        $vacio = ["anulados" => 0, "montoAnulado" => 0, "cortesias" => 0, "valorCortesias" => 0];

        return new Tablero(
            new Periodo($dias, new \DateTimeImmutable(self::AHORA)),
            $ventas,
            $incidencias ?? ["actual" => $vacio, "anterior" => $vacio],
            $salidas,
            $sinCerrar,
            [],
            $agencias,
            $facturacion,
            ["pendientes" => 0, "importe" => 0],
            $flota,
        );
    }

    public function testElPeriodoAnteriorSeCortaALaMismaHora(): void
    {
        $p = new Periodo(7, new \DateTimeImmutable(self::AHORA));
        $this->assertSame("2026-10-06 00:00", $p->inicio->format("Y-m-d H:i"));
        $this->assertSame("2026-09-29 00:00", $p->inicioAnterior->format("Y-m-d H:i"));
        $this->assertSame("2026-10-05 15:30", $p->finAnterior->format("Y-m-d H:i"));
        $this->assertSame("2026-09-15", $p->inicioDemanda()->format("Y-m-d"), "la demanda mira al menos cuatro semanas");
    }

    public function testHoySeComparaConElMismoDiaDeLaSemanaPasada(): void
    {
        $p = new Periodo(1, new \DateTimeImmutable(self::AHORA));
        $this->assertTrue($p->porHora());
        $this->assertSame("2026-10-05 00:00", $p->inicioAnterior->format("Y-m-d H:i"));
        $this->assertSame("2026-10-05 15:30", $p->finAnterior->format("Y-m-d H:i"));
        $this->assertSame("2026-10-12 09:00", $p->alActual(new \DateTimeImmutable("2026-10-05 09:00"))->format("Y-m-d H:i"));
    }

    public function testPeriodoInvalido(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Periodo(14, new \DateTimeImmutable(self::AHORA));
    }

    public function testOcupacionPesaPorAsientoTramoYOmiteCanceladasYSinCapacidad(): void
    {
        $ocupacion = Tablero::ocupacionDe([
            self::salida(1, "2026-10-10 08:00", ocupado: 40, asientos: 40),
            self::salida(2, "2026-10-10 09:00", ocupado: 30, asientos: 20, tramos: 3),
            self::salida(3, "2026-10-10 10:00", ocupado: 0, estado: "cancelada"),
            self::salida(4, "2026-10-10 11:00", ocupado: 5, asientos: 0),
        ]);
        $this->assertSame(round(70 / 100, 4), $ocupacion);
        $this->assertNull(Tablero::ocupacionDe([]));
    }

    public function testKpisSoloCuentanSalidasQueYaSalieron(): void
    {
        $t = self::tablero(
            ventas: [
                ["actual" => true, "t" => "2026-10-12 00:00", "canal" => "estacion", "ingresos" => 30000, "boletos" => 3],
                ["actual" => true, "t" => "2026-10-11 00:00", "canal" => "web", "ingresos" => 10000, "boletos" => 1],
                ["actual" => false, "t" => "2026-10-04 00:00", "canal" => "estacion", "ingresos" => 20000, "boletos" => 4],
            ],
            salidas: [
                self::salida(1, "2026-10-11 08:00", ocupado: 20, ingresos: 50000, boletos: 18, noPresentados: 2),
                self::salida(2, "2026-10-12 18:00", ocupado: 40, estado: "programada"),
                self::salida(3, "2026-10-04 08:00", ocupado: 10),
            ],
        );
        $k = $t->kpis();

        $this->assertSame(["actual" => 40000, "anterior" => 20000], $k["ingresos"]);
        $this->assertSame(["actual" => 10000, "anterior" => 5000], $k["ticketPromedio"]);
        $this->assertSame(["actual" => 0.5, "anterior" => 0.25], $k["ocupacion"], "la salida de las 18:00 aún no sale");
        $this->assertSame(0.1, $k["tasaNoPresentados"]["actual"]);
        $this->assertSame(50000, $k["ingresoPorSalida"]["actual"]);
    }

    public function testSerieAlineaElPeriodoAnteriorYDejaNuloLoQueNoHaLlegado(): void
    {
        $serie = self::tablero(dias: 1, ventas: [
            ["actual" => true, "t" => "2026-10-12 09:00", "canal" => "agencia", "ingresos" => 15000, "boletos" => 1],
            ["actual" => false, "t" => "2026-10-05 09:00", "canal" => "estacion", "ingresos" => 7000, "boletos" => 1],
        ])->serie();

        $this->assertCount(24, $serie);
        $this->assertSame(["estacion" => 0, "agencia" => 15000, "web" => 0, "total" => 15000, "boletos" => 1], $serie[9]["actual"]);
        $this->assertSame(["total" => 7000, "boletos" => 1], $serie[9]["anterior"]);
        $this->assertNotNull($serie[15]["actual"]);
        $this->assertNull($serie[16]["actual"], "las 16:00 todavía no llegan");
    }

    public function testSenalDeRutas(): void
    {
        $this->assertSame("saturada", Tablero::senalRuta(0.9, 5));
        $this->assertSame("baja", Tablero::senalRuta(0.2, 5));
        $this->assertNull(Tablero::senalRuta(0.6, 5));
        $this->assertNull(Tablero::senalRuta(0.1, 2), "con pocas salidas no se juzga");

        $rutas = self::tablero(salidas: [
            self::salida(1, "2026-10-10 08:00", ocupado: 38, ruta: 7, ingresos: 90000),
            self::salida(2, "2026-10-10 10:00", ocupado: 36, ruta: 7, ingresos: 90000),
            self::salida(3, "2026-10-11 08:00", ocupado: 37, ruta: 7, ingresos: 90000),
            self::salida(4, "2026-10-11 08:00", ocupado: 5, ruta: 8, ingresos: 300000),
        ])->rutas();
        $this->assertSame([8, 7], array_column($rutas, "id"), "por ingresos");
        $this->assertSame("saturada", $rutas[1]["senal"]);
        $this->assertSame(90000, $rutas[1]["ingresoPorSalida"]);
    }

    public function testDemandaYVentaAnticipada(): void
    {
        $t = self::tablero(salidas: [
            self::salida(1, "2026-10-05 08:10", ocupado: 30),
            self::salida(2, "2026-09-28 08:40", ocupado: 10),
            self::salida(3, "2026-10-13 08:00", ocupado: 4, estado: "programada"),
            self::salida(4, "2026-10-06 08:00", ocupado: 32),
        ]);
        $demanda = $t->demanda()["celdas"];
        $this->assertSame(["dia" => 1, "hora" => 8, "salidas" => 2, "ocupacion" => 0.5, "boletos" => 0], $demanda[0], "dos lunes a las 8");

        $anticipada = $t->anticipada();
        $this->assertCount(8, $anticipada);
        $this->assertSame("2026-10-13", $anticipada[1]["dia"]);
        $this->assertSame(0.1, $anticipada[1]["ocupacion"]);
        $this->assertSame(0.8, $anticipada[1]["semanaAnterior"]);
    }

    public function testAlertas(): void
    {
        $alertas = self::tablero(
            ventas: [
                ["actual" => true, "t" => "2026-10-12 00:00", "canal" => "estacion", "ingresos" => 50000, "boletos" => 5],
                ["actual" => false, "t" => "2026-10-05 00:00", "canal" => "estacion", "ingresos" => 100000, "boletos" => 10],
            ],
            salidas: [
                self::salida(1, "2026-10-12 17:00", ocupado: 2, estado: "programada"),
                self::salida(2, "2026-10-13 09:00", ocupado: 38, estado: "programada"),
                self::salida(3, "2026-10-13 05:00", ocupado: 0, estado: "programada"),
            ],
            sinCerrar: [["id" => 9, "fecha" => "2026-10-10 06:00", "ruta" => "R", "estado" => "iniciada", "llegada" => "2026-10-10 12:00"]],
            agencias: [
                ["id" => 1, "nombre" => "Tikal Tours", "saldo" => 10000, "moneda" => "GTQ", "ventas7" => 70000],
                ["id" => 2, "nombre" => "Holgada", "saldo" => 1000000, "moneda" => "GTQ", "ventas7" => 70000],
            ],
            facturacion: ["pendientes" => 3, "conError" => 1, "masAntigua" => "2026-10-10 08:00"],
            flota: ["buses" => 3, "vencidas" => [["id" => 1, "codigo" => "61", "vence" => "2026-01-01"]], "porVencer" => []],
        )->alertas();

        $this->assertSame(
            ["facturas_pendientes", "tarjetas_vencidas", "salidas_sin_cerrar", "agencias_sin_saldo", "ingresos_bajan", "salidas_llenas", "salidas_vacias"],
            array_column($alertas, "codigo"),
        );
        $this->assertSame("critico", $alertas[0]["nivel"], "factura pendiente de más de 24 h");
        $this->assertStringContainsString("Tikal Tours", $alertas[3]["detalle"]);
        $this->assertStringNotContainsString("Holgada", $alertas[3]["detalle"]);
        $this->assertSame("1 salida sale en menos de 6 h con menos del 15% vendido", $alertas[6]["titulo"]);
    }
}
