<?php

declare(strict_types=1);

namespace App\Tests\Sincronizacion;

use App\Sincronizacion\Legado\ImportadorLegado;
use PHPUnit\Framework\TestCase;

/** Qué busca el barrido de salidas. */
final class BarridoSalidasTest extends TestCase
{
    public function testDejaFueraLasQueYaEstan(): void
    {
        $candidatas = [
            ["id" => "1", "fecha" => "2026-11-07 08:00:00", "boletos" => "0"],
            ["id" => 2, "fecha" => "2026-11-08 08:00:00", "boletos" => "3"],
        ];

        self::assertSame([2], array_column(ImportadorLegado::faltantes($candidatas, ["1"]), "id"));
    }

    public function testPrimeroLasConBoletosYLasMasProximas(): void
    {
        $candidatas = [
            ["id" => 1, "fecha" => "2026-11-06 08:00:00", "boletos" => "0"],
            ["id" => 2, "fecha" => "2026-11-09 08:00:00", "boletos" => "5"],
            ["id" => 3, "fecha" => "2026-11-07 08:00:00", "boletos" => "1"],
            ["id" => 4, "fecha" => "2026-11-05 08:00:00", "boletos" => "0"],
        ];

        self::assertSame([3, 2, 4, 1], array_column(ImportadorLegado::faltantes($candidatas, []), "id"));
    }

    public function testElMotivoEsElDeLaSalidaNoElDeSusBoletos(): void
    {
        self::assertSame("salida_sin_bus_inferible", ImportadorLegado::motivoOmision(["omitido_boleto_sin_asiento" => 1, "omitido_salida_sin_bus_inferible" => 1]));
        self::assertNull(ImportadorLegado::motivoOmision(["salida" => 1, "omitido_boleto_sin_asiento" => 1]));
    }
}
