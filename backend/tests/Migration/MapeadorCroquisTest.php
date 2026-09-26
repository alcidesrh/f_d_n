<?php

declare(strict_types=1);

namespace App\Tests\Migration;

use App\Entity\Enum\TipoBusSenal;
use App\Migration\Mapeador;
use PHPUnit\Framework\TestCase;

final class MapeadorCroquisTest extends TestCase
{
    public function testAsientoConvierteCoordenadasYPlanta(): void
    {
        $data = (new Mapeador())->asiento(
            ['id' => 900, 'numero' => 7, 'clase_id' => 2, 'nivel2' => '0', 'coordenadaX' => 150, 'coordenadaY' => 100],
            12,
        );

        $this->assertSame(
            ['numero' => 7, 'clase' => 'B', 'planta' => 1, 'fila' => 3, 'columna' => 4, 'bus_id' => 12],
            $data,
        );
    }

    public function testAsientoDelSegundoNivelVaALaPlantaAlta(): void
    {
        $data = (new Mapeador())->asiento(['numero' => 1, 'clase_id' => 1, 'nivel2' => 1, 'coordenadaX' => 0, 'coordenadaY' => 0], 1);

        $this->assertSame(2, $data['planta']);
        $this->assertSame([1, 1], [$data['fila'], $data['columna']]);
    }

    public function testSenalReconoceElTipoPorNombre(): void
    {
        $data = (new Mapeador())->senal(
            ['tipo_nombre' => 'PUERTA', 'nivel2' => false, 'coordenada_x' => 200, 'coordenada_y' => 50],
            3,
        );

        $this->assertSame(['tipo' => 'puerta', 'planta' => 1, 'fila' => 2, 'columna' => 5, 'bus_id' => 3], $data);
    }

    public function testSenalDeTipoDesconocidoSeOmite(): void
    {
        $this->assertNull((new Mapeador())->senal(['tipo_nombre' => 'Televisor', 'coordenada_x' => 0, 'coordenada_y' => 0], 3));
    }

    public function testNombresLegadoDeTipoDeSenal(): void
    {
        $this->assertSame(TipoBusSenal::CHOFER, TipoBusSenal::desdeNombreLegado('Chofer'));
        $this->assertSame(TipoBusSenal::CHOFER, TipoBusSenal::desdeNombreLegado(' piloto '));
        $this->assertSame(TipoBusSenal::PUERTA, TipoBusSenal::desdeNombreLegado('Puerta trasera'));
        $this->assertNull(TipoBusSenal::desdeNombreLegado(''));
        $this->assertNull(TipoBusSenal::desdeNombreLegado(null));
    }
}
