<?php

declare(strict_types=1);

namespace App\Tests\Chat;

use App\Chat\Predeterminado\DesempenoDelDia;
use App\Reporte\Dinero;
use App\Tablero\ConsultaTablero;
use PHPUnit\Framework\TestCase;

/** Las cifras de "Desempeño del día" (ADR-045): ingresos, canales, operación y ranking. */
final class DesempenoDelDiaTest extends TestCase
{
    private const SIN_OPERACION = ["anulados" => 0, "reasignados" => 0, "cancelados" => 0, "cortesias" => 0, "vouchers" => 0];

    private static function q(int $centavos): string
    {
        return Dinero::con($centavos, ConsultaTablero::MONEDA);
    }

    public function testLosDatosDelaTarjetaTraenPorcentajesYMontos(): void
    {
        $datos = DesempenoDelDia::datos(
            "Desempeño",
            "Empresa",
            [
                ["tipo" => "estacion", "nombre" => "Guatemala", "intermedia" => false, "boletos" => 3, "ingresos" => 30000],
                ["tipo" => "estacion", "nombre" => "Zacapa", "intermedia" => true, "boletos" => 1, "ingresos" => 10000],
            ],
            [],
            self::SIN_OPERACION,
        );

        self::assertFalse($datos["vacio"]);
        self::assertSame(75.0, $datos["ruta"]["porcentaje"]);
        self::assertSame(25.0, $datos["intermedia"]["porcentaje"]);
        self::assertSame(100.0, $datos["ranking"][0]["porcentaje"], "la barra de la primera estación es el máximo");
        self::assertSame(33.33, $datos["ranking"][1]["porcentaje"]);
        self::assertSame(100.0, $datos["canales"][0]["porcentaje"], "estaciones: todo lo vendido en boletos");
    }

    public function testSeparaRutaCompletaIntermediaYEncomiendas(): void
    {
        $datos = DesempenoDelDia::datos(
            "Desempeño del lunes",
            "Empresa",
            [
                ["tipo" => "estacion", "nombre" => "Chiquimula", "intermedia" => false, "boletos" => 3, "ingresos" => 30000],
                ["tipo" => "estacion", "nombre" => "Chiquimula", "intermedia" => true, "boletos" => 2, "ingresos" => 10000],
                ["tipo" => "web", "nombre" => "Página web", "intermedia" => false, "boletos" => 1, "ingresos" => 15000],
            ],
            [["nombre" => "Chiquimula", "cantidad" => 4, "ingresos" => 20000]],
            self::SIN_OPERACION,
        );

        self::assertSame("Empresa", $datos["empresa"]);
        self::assertSame(self::q(75000), $datos["total"]);
        self::assertSame(["ingresos" => self::q(55000), "cantidad" => 6], $datos["boletos"]);
        self::assertSame(["ingresos" => self::q(20000), "cantidad" => 4], $datos["encomiendas"]);
        self::assertSame(self::q(45000), $datos["ruta"]["ingresos"]);
        self::assertSame(4, $datos["ruta"]["cantidad"]);
        self::assertSame(self::q(10000), $datos["intermedia"]["ingresos"]);
        self::assertSame(2, $datos["intermedia"]["cantidad"]);
        self::assertSame(self::q(15000), $datos["canales"][2]["ingresos"]);
        self::assertSame(1, $datos["canales"][2]["boletos"]);
    }

    public function testElRankingSumaBoletosYEncomiendasPorEstacion(): void
    {
        $datos = DesempenoDelDia::datos(
            "Desempeño",
            "Empresa",
            [
                ["tipo" => "estacion", "nombre" => "Guatemala", "intermedia" => false, "boletos" => 1, "ingresos" => 10000],
                ["tipo" => "estacion", "nombre" => "Zacapa", "intermedia" => false, "boletos" => 1, "ingresos" => 25000],
            ],
            [["nombre" => "Guatemala", "cantidad" => 2, "ingresos" => 20000]],
            self::SIN_OPERACION,
        );

        // Guatemala (30000) va antes que Zacapa (25000).
        self::assertSame(["nombre" => "Guatemala", "ingresos" => self::q(30000), "boletos" => 1, "encomiendas" => 2, "porcentaje" => 100.0], $datos["ranking"][0]);
        self::assertSame(["nombre" => "Zacapa", "ingresos" => self::q(25000), "boletos" => 1, "encomiendas" => 0, "porcentaje" => 83.33], $datos["ranking"][1]);
    }

    public function testLaOperacionDelDiaIncluyeVouchers(): void
    {
        $datos = DesempenoDelDia::datos(
            "Desempeño",
            "Empresa",
            [["tipo" => "agencia", "nombre" => "Agencia Norte", "intermedia" => false, "boletos" => 1, "ingresos" => 5000]],
            [],
            ["anulados" => 2, "reasignados" => 3, "cancelados" => 1, "cortesias" => 4, "vouchers" => 5],
        );

        self::assertSame(["reasignados" => 3, "anulados" => 2, "cancelados" => 1, "cortesias" => 4, "vouchers" => 5], $datos["operacion"]);
        self::assertSame([], $datos["ranking"], "sin estaciones no hay ranking");
    }

    public function testUnDiaSinVentas(): void
    {
        $datos = DesempenoDelDia::datos("Desempeño del martes", "Empresa", [], [], self::SIN_OPERACION);

        self::assertTrue($datos["vacio"]);
        self::assertSame("Desempeño del martes", $datos["titulo"]);
    }
}
