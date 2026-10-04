<?php

declare(strict_types=1);

namespace App\Tests\Salida;

use App\Salida\DetalleSalida;
use App\Salida\Manifiesto\Manifiesto;
use App\Salida\Manifiesto\Pasajero;
use PHPUnit\Framework\TestCase;

/** Reglas puras de los manifiestos y del resumen de "Ver" una salida. */
final class ManifiestoTest extends TestCase
{
    private static function pasajero(int $asiento, string $emitidoEn, int $centavos = 15000, bool $sinCobro = false, string $canal = "estacion"): Pasajero
    {
        return new Pasajero(100 + $asiento, "PASAJERO {$asiento}", "Guatemalteca", $sinCobro ? "Voucher" : "AG 1", $asiento, $asiento > 50 ? "B" : "A", "A", "B", "emitido", $canal, $centavos, "GTQ", $sinCobro, $emitidoEn, null);
    }

    private static function manifiesto(Pasajero ...$p): Manifiesto
    {
        return new Manifiesto(1, new \DateTimeImmutable("2026-10-01 01:00"), "A", "A - B", "Empresa", "0322", ["M040 - WALTER", "N/D"], array_values($p));
    }

    public function testOrdenaPorAsientoYAgrupaPorEmisionConSinLugarAlFinal(): void
    {
        $m = self::manifiesto(self::pasajero(9, "—"), self::pasajero(5, "Guatemala"), self::pasajero(3, "Aguilar Batres"), self::pasajero(1, "Guatemala"));

        $this->assertSame([1, 3, 5, 9], array_map(static fn(Pasajero $p) => $p->asiento, $m->pasajeros));
        $this->assertSame(["Aguilar Batres", "Guatemala", "—"], array_keys($m->porEmision()));
        $this->assertSame([1, 5], array_map(static fn(Pasajero $p) => $p->asiento, $m->porEmision()["Guatemala"]));
    }

    public function testLosBoletosSinCobroNoSumanAlImporte(): void
    {
        $m = self::manifiesto(self::pasajero(1, "X"), self::pasajero(2, "X", 10000, true), self::pasajero(3, "X", 5050));

        $this->assertSame(20050, Manifiesto::cobrado($m->pasajeros));
        $this->assertSame("Q 200.50", Manifiesto::importe(20050));
        $this->assertSame("Q 0.00", $m->pasajeros[1]->importe());
    }

    public function testResumenPorClaseCanalYReservas(): void
    {
        $croquis = [
            ["tipo" => "chofer", "id" => 1],
            ["tipo" => "asiento", "id" => 10, "clase" => "A"],
            ["tipo" => "asiento", "id" => 11, "clase" => "A"],
            ["tipo" => "asiento", "id" => 12, "clase" => "B"],
            ["tipo" => "asiento", "id" => 13, "clase" => "B"],
        ];
        $ocupados = [
            ["asiento" => 10, "estado" => "vendido", "canal" => "web", "sinCobro" => null],
            ["asiento" => 12, "estado" => "vendido", "canal" => "estacion", "sinCobro" => "voucher"],
            ["asiento" => 13, "estado" => "reservado", "canal" => null, "sinCobro" => null],
        ];

        $r = DetalleSalida::resumen($croquis, $ocupados, self::manifiesto(self::pasajero(1, "X"), self::pasajero(2, "X", 10000, true)));

        $this->assertSame(4, $r["asientos"]);
        $this->assertSame(2, $r["vendidos"]);
        $this->assertSame(1, $r["reservados"]);
        $this->assertSame(
            [["clase" => "A", "asientos" => 2, "vendidos" => 1, "reservados" => 0], ["clase" => "B", "asientos" => 2, "vendidos" => 1, "reservados" => 1]],
            $r["clases"],
        );
        $this->assertSame(["estacion" => 0, "agencia" => 0, "web" => 1, "cortesia" => 0, "voucher" => 1], $r["canales"]);
        $this->assertSame("Q 150.00", $r["ingresos"]["texto"]);
    }
}
