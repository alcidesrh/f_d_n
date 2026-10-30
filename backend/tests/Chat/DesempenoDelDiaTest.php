<?php

declare(strict_types=1);

namespace App\Tests\Chat;

use App\Chat\Predeterminado\DesempenoDelDia;
use App\Reporte\Dinero;
use App\Tablero\ConsultaTablero;
use PHPUnit\Framework\TestCase;

/** El texto de "Desempeño del día" (ADR-045): ingresos, canales, operación y ranking. */
final class DesempenoDelDiaTest extends TestCase
{
    private const SIN_OPERACION = ["anulados" => 0, "reasignados" => 0, "cancelados" => 0, "cortesias" => 0];

    public function testSeparaRutaCompletaIntermediaYEncomiendas(): void
    {
        $texto = DesempenoDelDia::texto(
            "Desempeño del lunes · Empresa",
            [
                ["tipo" => "estacion", "nombre" => "Chiquimula", "intermedia" => false, "boletos" => 3, "ingresos" => 30000],
                ["tipo" => "estacion", "nombre" => "Chiquimula", "intermedia" => true, "boletos" => 2, "ingresos" => 10000],
                ["tipo" => "web", "nombre" => "Página web", "intermedia" => false, "boletos" => 1, "ingresos" => 15000],
            ],
            [["nombre" => "Chiquimula", "cantidad" => 4, "ingresos" => 20000]],
            self::SIN_OPERACION,
        );

        self::assertStringContainsString("Ingresos totales: " . Dinero::con(75000, ConsultaTablero::MONEDA), $texto);
        self::assertStringContainsString("• Boletos: " . Dinero::con(55000, ConsultaTablero::MONEDA) . " · 6", $texto);
        self::assertStringContainsString("• Encomiendas: " . Dinero::con(20000, ConsultaTablero::MONEDA) . " · 4", $texto);
        self::assertStringContainsString("• Ruta completa: " . Dinero::con(45000, ConsultaTablero::MONEDA) . " · 4", $texto);
        self::assertStringContainsString("• Intermedia: " . Dinero::con(10000, ConsultaTablero::MONEDA) . " · 2", $texto);
        self::assertStringContainsString("• Página web: " . Dinero::con(15000, ConsultaTablero::MONEDA) . " · 1", $texto);
    }

    public function testElRankingSumaBoletosYEncomiendasPorEstacion(): void
    {
        $texto = DesempenoDelDia::texto(
            "Desempeño",
            [
                ["tipo" => "estacion", "nombre" => "Guatemala", "intermedia" => false, "boletos" => 1, "ingresos" => 10000],
                ["tipo" => "estacion", "nombre" => "Zacapa", "intermedia" => false, "boletos" => 1, "ingresos" => 25000],
            ],
            [["nombre" => "Guatemala", "cantidad" => 2, "ingresos" => 20000]],
            self::SIN_OPERACION,
        );

        // Guatemala (30000) va antes que Zacapa (25000).
        self::assertStringContainsString("1. Guatemala · " . Dinero::con(30000, ConsultaTablero::MONEDA) . " (boletos 1 · encomiendas 2)", $texto);
        self::assertStringContainsString("2. Zacapa · " . Dinero::con(25000, ConsultaTablero::MONEDA) . " (boletos 1 · encomiendas 0)", $texto);
    }

    public function testLaOperacionDelDia(): void
    {
        $texto = DesempenoDelDia::texto(
            "Desempeño",
            [["tipo" => "agencia", "nombre" => "Agencia Norte", "intermedia" => false, "boletos" => 1, "ingresos" => 5000]],
            [],
            ["anulados" => 2, "reasignados" => 3, "cancelados" => 1, "cortesias" => 4],
        );

        self::assertStringContainsString("• Reasignados: 3 · Anulados: 2 · Cancelados: 1 · Cortesías: 4", $texto);
        self::assertStringNotContainsString("Estaciones con más ventas", $texto, "sin estaciones no hay ranking");
    }

    public function testUnDiaSinVentas(): void
    {
        $texto = DesempenoDelDia::texto("Desempeño del martes · Empresa", [], [], self::SIN_OPERACION);

        self::assertSame("Desempeño del martes · Empresa\nHoy no hubo ventas ni encomiendas.", $texto);
    }
}
