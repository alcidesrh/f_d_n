<?php

declare(strict_types=1);

namespace App\Tests\Migration\Entidad;

use App\Migration\Entidad\Especificacion;
use PHPUnit\Framework\TestCase;

final class EspecificacionTest extends TestCase
{
    public function testDesdeArrayNormalizaValores(): void
    {
        $es = Especificacion::desdeArray([
            "entidad" => "salida",
            "desde" => "2024-01-01",
            "hasta" => "2024-01-31",
            "cantidad" => "150",
        ]);

        self::assertSame("salida", $es->entidad);
        self::assertSame("2024-01-01", $es->desde);
        self::assertSame("2024-01-31", $es->hasta);
        self::assertSame(150, $es->cantidad);
    }

    public function testDesdeArrayVaciaYConCantidadInvalida(): void
    {
        $es = Especificacion::desdeArray([
            "entidad" => "empresa",
            "desde" => "",
            "hasta" => null,
            "cantidad" => 0,
        ]);

        self::assertNull($es->desde);
        self::assertNull($es->hasta);
        self::assertNull($es->cantidad);
    }

    public function testParaMantieneRangoYCantidad(): void
    {
        $es = Especificacion::desdeArray([
            "entidad" => "tarifa",
            "desde" => "2024-02-01",
            "hasta" => "2024-02-28",
            "cantidad" => 10,
        ]);

        $dep = $es->para("trayecto");
        self::assertSame("trayecto", $dep->entidad);
        self::assertSame("2024-02-01", $dep->desde);
        self::assertSame("2024-02-28", $dep->hasta);
        self::assertSame(10, $dep->cantidad);
    }

    public function testToArrayEsRedondo(): void
    {
        $es = Especificacion::desdeArray([
            "entidad" => "salida",
            "desde" => "2024-03-01",
            "cantidad" => 25,
        ]);

        $vuelta = Especificacion::desdeArray($es->toArray());
        self::assertEquals($es, $vuelta);
    }
}
