<?php

declare(strict_types=1);

namespace App\Tests\Venta;

use App\Venta\EnLinea\SeguimientoViaje;
use PHPUnit\Framework\TestCase;

/** «¿Dónde va mi bus?» (ADR-065): el tramo del cliente y cuándo se muestra. */
final class SeguimientoViajeTest extends TestCase
{
    private const HORA = 1_800_000_000;

    /** @return list<array{enclave: int, nombre: string, programada: ?int, estimada: ?int, paso: bool}> */
    private static function paradas(): array
    {
        return array_map(
            static fn(int $i) => ["enclave" => $i, "nombre" => "Parada {$i}", "programada" => self::HORA + $i * 3600, "estimada" => self::HORA + $i * 3600, "paso" => false],
            [0, 1, 2, 3],
        );
    }

    public function testSoloLasParadasEntreDondeSubeYDondeBaja(): void
    {
        $this->assertSame([1, 2], array_column(SeguimientoViaje::tramo(self::paradas(), 1, 2), "enclave"));
        $this->assertSame([0, 1, 2, 3], array_column(SeguimientoViaje::tramo(self::paradas(), 0, 3), "enclave"));
        $this->assertSame([], SeguimientoViaje::tramo(self::paradas(), 2, 1), "hacia atrás");
        $this->assertSame([], SeguimientoViaje::tramo(self::paradas(), 1, 9), "no es parada de la salida");
    }

    public function testSeMuestraDesdeTresHorasAntesHastaSeisDespues(): void
    {
        $tramo = SeguimientoViaje::tramo(self::paradas(), 1, 2);
        $sube = self::HORA + 3600;
        $baja = self::HORA + 7200;

        $this->assertFalse(SeguimientoViaje::visible($tramo, $sube - 3 * 3600 - 1));
        $this->assertTrue(SeguimientoViaje::visible($tramo, $sube - 3 * 3600));
        $this->assertTrue(SeguimientoViaje::visible($tramo, $baja + 6 * 3600));
        $this->assertFalse(SeguimientoViaje::visible($tramo, $baja + 6 * 3600 + 1));
        $this->assertFalse(SeguimientoViaje::visible([], $sube));
    }
}
