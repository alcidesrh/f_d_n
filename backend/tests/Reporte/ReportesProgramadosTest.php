<?php

declare(strict_types=1);

namespace App\Tests\Reporte;

use App\Acceso\Alcance;
use App\Acceso\Ambito;
use App\Reporte\Diario\Celdas;
use App\Reporte\Diario\ReporteProgramado;
use App\Reporte\Diario\ResumenDiario;
use PHPUnit\Framework\TestCase;

/** Reportes programados (ADR-044): el recorte por alcance y el texto del resumen. */
final class ReportesProgramadosTest extends TestCase
{
    public function testLasCeldasSiguenElAlcanceSinRepetir(): void
    {
        $alcance = new Alcance([
            new Ambito([1], null),
            new Ambito([1], [10]), // cubierta por la empresa 1 entera
            new Ambito([2], [20, 21]),
            new Ambito(null, null, agencia: 7), // las agencias no cuentan
        ]);

        self::assertSame(
            [["empresa" => 1, "estacion" => null], ["empresa" => 2, "estacion" => 20], ["empresa" => 2, "estacion" => 21]],
            Celdas::de($alcance, ReporteProgramado::ESTACION),
        );
        // Por empresa: la 2 solo la ve por estaciones, no entera.
        self::assertSame([["empresa" => 1, "estacion" => null]], Celdas::de($alcance, ReporteProgramado::EMPRESA));
        self::assertSame([["empresa" => null, "estacion" => null]], Celdas::de(Alcance::total(), ReporteProgramado::EMPRESA));
        self::assertSame([], Celdas::de(Alcance::nada(), ReporteProgramado::EMPRESA));
    }

    public function testElAlcanceDeUnaCelda(): void
    {
        $a = Celdas::alcanceDe(["empresa" => 2, "estacion" => 20])->ambitos()[0];
        self::assertSame([2], $a->empresas);
        self::assertSame([20], $a->estaciones);
        self::assertTrue(Celdas::alcanceDe(["empresa" => null, "estacion" => null])->sinRestriccion());
    }

    public function testElTextoDelResumenParaElChat(): void
    {
        $texto = ResumenDiario::texto("Resumen del miércoles 28 de octubre", [[
            "empresa" => "PIONERA",
            "ingresos" => "Q 12,500.00",
            "variacion" => "+8 %",
            "boletos" => 140,
            "ocupacion" => "72 %",
            "salidas" => 12,
            "canceladas" => 1,
            "anulados" => 3,
            "montoAnulado" => "Q 300.00",
            "cortesias" => 2,
            "noPresentados" => null,
            "quien" => ["Anulaciones" => "Ana Pérez 3"],
            "alertas" => [["nivel" => "Crítico", "titulo" => "2 buses con tarjeta vencida", "detalle" => "B1, B2."]],
        ]]);

        self::assertStringContainsString("• Ingresos: Q 12,500.00 (+8 % vs. el mismo día de la semana pasada) · 140 boletos", $texto);
        self::assertStringContainsString("• 12 salidas, ocupación 72 % (1 anuladas)", $texto);
        self::assertStringContainsString("• Anulaciones: Ana Pérez 3", $texto);
        self::assertStringContainsString("• [Crítico] 2 buses con tarjeta vencida. B1, B2.", $texto);
    }

    public function testElTextoLargoSeCortaAntesDelLimiteDelChat(): void
    {
        $seccion = ["empresa" => str_repeat("X", 500), "ingresos" => "Q 0", "variacion" => null, "boletos" => 0, "ocupacion" => null, "salidas" => 0, "canceladas" => 0, "anulados" => 0, "montoAnulado" => "Q 0", "cortesias" => 0, "noPresentados" => null, "quien" => [], "alertas" => []];
        self::assertLessThanOrEqual(3900, mb_strlen(ResumenDiario::texto("T", array_fill(0, 20, $seccion))));
    }
}
