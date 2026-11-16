<?php

declare(strict_types=1);

namespace App\Tests\Mantenimiento;

use App\Mantenimiento\AvisosMantenimiento;
use App\Mantenimiento\ReglasMantenimiento as R;
use PHPUnit\Framework\TestCase;

/** Cuándo vence un plan de mantenimiento y qué se avisa (ADR-065). */
final class ReglasMantenimientoTest extends TestCase
{
    public function testSinOrdenesNoHayDesdeDondeContar(): void
    {
        $this->assertSame(["estado" => R::SIN_REGISTRO, "faltanKm" => null, "faltanDias" => null], R::estado(10000, 180, null, null, 50000, "2026-11-16"));
    }

    public function testPorKilometros(): void
    {
        // Cada 10 000 km, hecho a los 40 000: avisa cuando le faltan 1 000 o menos.
        $this->assertSame(["estado" => R::AL_DIA, "faltanKm" => 1001, "faltanDias" => null], R::estado(10000, null, 40000, "2026-01-01", 48999, "2026-11-16"));
        $this->assertSame(R::POR_VENCER, R::estado(10000, null, 40000, "2026-01-01", 49000, "2026-11-16")["estado"]);
        $this->assertSame(["estado" => R::VENCIDO, "faltanKm" => 0, "faltanDias" => null], R::estado(10000, null, 40000, "2026-01-01", 50000, "2026-11-16"));
        $this->assertSame(-320, R::estado(10000, null, 40000, "2026-01-01", 50320, "2026-11-16")["faltanKm"]);
    }

    public function testPorDias(): void
    {
        // Cada 180 días, hecho el 1 de junio: vence el 28 de noviembre y avisa 18 días antes.
        $this->assertSame(["estado" => R::AL_DIA, "faltanKm" => null, "faltanDias" => 19], R::estado(null, 180, 40000, "2026-06-01", 90000, "2026-11-09"));
        $this->assertSame(R::POR_VENCER, R::estado(null, 180, 40000, "2026-06-01", 90000, "2026-11-10")["estado"]);
        $this->assertSame(["estado" => R::VENCIDO, "faltanKm" => null, "faltanDias" => 0], R::estado(null, 180, 40000, "2026-06-01", 90000, "2026-11-28"));
        $this->assertSame(-3, R::estado(null, 180, 40000, "2026-06-01", 90000, "2026-12-01")["faltanDias"]);
    }

    public function testLoQueLlegaPrimero(): void
    {
        // Le sobran kilómetros pero ya pasaron los días.
        $r = R::estado(10000, 180, 40000, "2026-01-01", 41000, "2026-11-16");

        $this->assertSame(R::VENCIDO, $r["estado"]);
        $this->assertSame(9000, $r["faltanKm"]);
        $this->assertLessThan(0, $r["faltanDias"]);
    }

    public function testUnaOrdenSinOdometroSoloCuentaPorFecha(): void
    {
        $this->assertSame(["estado" => R::AL_DIA, "faltanKm" => null, "faltanDias" => 170], R::estado(10000, 180, null, "2026-11-06", 90000, "2026-11-16"));
    }

    public function testElPeorEstado(): void
    {
        $this->assertSame(R::AL_DIA, R::peor([]));
        $this->assertSame(R::SIN_REGISTRO, R::peor([R::AL_DIA, R::SIN_REGISTRO]));
        $this->assertSame(R::POR_VENCER, R::peor([R::SIN_REGISTRO, R::POR_VENCER, R::AL_DIA]));
        $this->assertSame(R::VENCIDO, R::peor([R::POR_VENCER, R::VENCIDO]));
    }

    public function testDetalle(): void
    {
        $this->assertSame("vencido hace 1,320 km", R::detalle(R::VENCIDO, -1320, 40));
        $this->assertSame("vencido hace 320 km y 1 día", R::detalle(R::VENCIDO, -320, -1));
        $this->assertSame("vence hoy", R::detalle(R::VENCIDO, 500, 0));
        $this->assertSame("le faltan 800 km o 12 días", R::detalle(R::POR_VENCER, 800, 12));
        $this->assertSame("le faltan 12 días", R::detalle(R::POR_VENCER, null, 12));
        $this->assertSame("sin registro", R::detalle(R::SIN_REGISTRO, null, null));
    }

    public function testSeAvisaUnaVezPorEstado(): void
    {
        $buses = [
            ["id" => 1, "codigo" => "105", "matricula" => "C-123BBB", "empresaId" => 7, "planes" => [
                ["plan" => 1, "estado" => R::VENCIDO, "faltanKm" => -320, "faltanDias" => null],
                ["plan" => 2, "estado" => R::AL_DIA, "faltanKm" => null, "faltanDias" => 90],
            ]],
            ["id" => 2, "codigo" => "090", "matricula" => null, "empresaId" => 7, "planes" => [
                ["plan" => 1, "estado" => R::POR_VENCER, "faltanKm" => 800, "faltanDias" => null],
                ["plan" => 2, "estado" => R::POR_VENCER, "faltanKm" => null, "faltanDias" => 5],
            ]],
            ["id" => 3, "codigo" => "200", "matricula" => null, "empresaId" => 8, "planes" => [
                ["plan" => 1, "estado" => R::POR_VENCER, "faltanKm" => 100, "faltanDias" => null],
                ["plan" => 2, "estado" => R::SIN_REGISTRO, "faltanKm" => null, "faltanDias" => null],
            ]],
        ];
        // Ya avisados: el 1:1 como por vencer (ahora venció: avisa), el 2:2 como por vencer (igual: no),
        // el 3:1 como vencido (bajó a por vencer: no) y el 9:9, que ya no aplica.
        $r = AvisosMantenimiento::nuevos($buses, [1 => "Cambio de aceite", 2 => "Frenos"], ["1:1" => R::POR_VENCER, "2:2" => R::POR_VENCER, "3:1" => R::VENCIDO, "9:9" => R::VENCIDO]);

        $this->assertSame([[1, 1], [2, 1]], array_map(static fn(array $n) => [$n["busId"], $n["planId"]], $r["nuevos"]));
        $this->assertSame(["1:1", "2:1", "2:2", "3:1"], array_keys($r["vigentes"]));
        $this->assertSame(
            "Mantenimiento de flota: 2 servicios piden atención.\n• Bus 105 (C-123BBB): Cambio de aceite, vencido hace 320 km.\n• Bus 090: Cambio de aceite, le faltan 800 km.",
            AvisosMantenimiento::texto($r["nuevos"]),
        );
        $this->assertStringStartsWith("Mantenimiento de flota: un servicio pide atención.", AvisosMantenimiento::texto([$r["nuevos"][0]]));
    }
}
