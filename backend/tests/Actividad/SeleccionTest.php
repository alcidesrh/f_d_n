<?php

declare(strict_types=1);

namespace App\Tests\Actividad;

use App\Actividad\Seleccion;
use App\Actividad\Sujeto;
use PHPUnit\Framework\TestCase;

final class SeleccionTest extends TestCase
{
    private static function hoy(): \DateTimeImmutable
    {
        return new \DateTimeImmutable("2026-11-05 10:00");
    }

    public function testIncluyePorUsuarioRolEmpresaYEstacion(): void
    {
        $s = new Seleccion([
            ["tipo" => "usuario", "id" => 7],
            ["tipo" => "rol", "id" => 3],
            ["tipo" => "empresa", "id" => 2],
            ["tipo" => "estacion", "id" => 40],
        ]);

        $this->assertTrue($s->incluye(new Sujeto(7), self::hoy()));
        $this->assertTrue($s->incluye(new Sujeto(8, [1, 3]), self::hoy()));
        $this->assertTrue($s->incluye(new Sujeto(9, [], [2, 5]), self::hoy()));
        $this->assertTrue($s->incluye(new Sujeto(10, [], [], 40), self::hoy()), "por su estación de trabajo");
        $this->assertFalse($s->incluye(new Sujeto(11, [1], [5], 41), self::hoy()));
    }

    public function testUnAlcanceSinRestriccionDeEmpresaEstaEnTodas(): void
    {
        $s = new Seleccion([["tipo" => "empresa", "id" => 2]]);

        $this->assertTrue($s->incluye(new Sujeto(1, [], null), self::hoy()));
    }

    public function testLasFechasAcotanLaEntradaYHastaIncluyeEseDia(): void
    {
        $s = new Seleccion([["tipo" => "usuario", "id" => 7, "desde" => "2026-11-01", "hasta" => "2026-11-05"]]);

        $this->assertTrue($s->incluye(new Sujeto(7), new \DateTimeImmutable("2026-11-05 23:59")));
        $this->assertFalse($s->incluye(new Sujeto(7), new \DateTimeImmutable("2026-11-06 00:00")), "se apaga sola");
        $this->assertFalse($s->incluye(new Sujeto(7), new \DateTimeImmutable("2026-10-31 12:00")), "todavía no empieza");
    }

    public function testValidarRechazaLoMalFormadoYNormaliza(): void
    {
        $this->assertSame(
            [
                ["tipo" => "usuario", "id" => 7, "desde" => null, "hasta" => null],
                ["tipo" => "rol", "id" => 3, "desde" => null, "hasta" => "2026-12-31"],
            ],
            Seleccion::validar([
                ["tipo" => "rol", "id" => "3", "hasta" => "2026-12-31"],
                ["tipo" => "usuario", "id" => 7, "desde" => ""],
                ["tipo" => "usuario", "id" => 7],
            ]),
        );

        foreach ([
            "no es lista" => ["tipo" => "usuario"],
            "tipo desconocido" => [["tipo" => "agencia", "id" => 1]],
            "sin id" => [["tipo" => "usuario"]],
            "fecha mala" => [["tipo" => "usuario", "id" => 1, "desde" => "05/11/2026"]],
            "desde después de hasta" => [["tipo" => "usuario", "id" => 1, "desde" => "2026-12-01", "hasta" => "2026-11-01"]],
        ] as $caso => $entrada) {
            try {
                Seleccion::validar($entrada);
                $this->fail("Debió rechazar: {$caso}");
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }
}
