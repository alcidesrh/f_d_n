<?php

declare(strict_types=1);

namespace App\Tests\Croquis;

use App\Croquis\Croquis;
use App\Croquis\ElementoCroquis;
use App\Croquis\Moldes;
use App\Entity\Enum\AsientoClase;
use App\Migration\Mapeador;
use App\Migration\Salida\DependenciasLegado;
use App\Migration\Salida\InferenciaBus;
use App\Migration\Salida\LectorLegado;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class MoldesTest extends TestCase
{
    public function testFirmaIgnoraIdsYOrden(): void
    {
        $a = [self::asiento(1, 1, 1, 10), self::asiento(2, 1, 2, 11), new ElementoCroquis("chofer", 1, 1, 4, id: 5)];
        $b = [new ElementoCroquis("chofer", 1, 1, 4), self::asiento(2, 1, 2), self::asiento(1, 1, 1)];

        $this->assertSame(Moldes::firma($a), Moldes::firma($b));
        $this->assertSame(Croquis::firma($a), Moldes::firma($a));
    }

    public function testSinAsientosNoHayMolde(): void
    {
        $this->assertNull(Moldes::firma([]));
        $this->assertNull(Moldes::firma([new ElementoCroquis("chofer", 1, 1, 1), new ElementoCroquis("puerta", 1, 2, 1)]));
    }

    public function testOtraClaseUOtroNumeroEsOtroMolde(): void
    {
        $base = [self::asiento(1, 1, 1)];

        $this->assertNotSame(Moldes::firma($base), Moldes::firma([self::asiento(2, 1, 1)]));
        $this->assertNotSame(Moldes::firma($base), Moldes::firma([new ElementoCroquis(ElementoCroquis::ASIENTO, 1, 1, 1, 1, AsientoClase::B)]));
    }

    /** El molde de un tipo del legado se arma como la migración copia el croquis al bus: sin números ni celdas repetidos. */
    public function testFirmaDeTipoComoLaCopiaDeLaMigracion(): void
    {
        $legado = $this->createStub(LectorLegado::class);
        $legado->method("filas")->willReturnCallback(static fn(string $sql) => str_contains($sql, "bus_asiento")
            ? [
                ["numero" => 1, "clase_id" => 1, "nivel2" => 0, "coordenadaX" => 0, "coordenadaY" => 0],
                ["numero" => 2, "clase_id" => 1, "nivel2" => 0, "coordenadaX" => 50, "coordenadaY" => 0],
                ["numero" => 2, "clase_id" => 1, "nivel2" => 0, "coordenadaX" => 150, "coordenadaY" => 0],
            ]
            : [
                ["tipo_nombre" => "CHOFER", "nivel2" => 0, "coordenada_x" => 200, "coordenada_y" => 0],
                ["tipo_nombre" => "CHOFER", "nivel2" => 0, "coordenada_x" => 200, "coordenada_y" => 0],
            ]);
        $inferencia = new InferenciaBus(
            $this->createStub(Connection::class),
            $legado,
            new Mapeador(),
            $this->createStub(DependenciasLegado::class),
        );

        $bus = [self::asiento(1, 1, 1), self::asiento(2, 1, 2), new ElementoCroquis("chofer", 1, 1, 5)];
        $this->assertSame(Moldes::firma($bus), $inferencia->firmaDeTipo(7));
    }

    public function testSinTipoDeBusNoSeInfiere(): void
    {
        $inferencia = new InferenciaBus(
            $this->createStub(Connection::class),
            $this->createStub(LectorLegado::class),
            new Mapeador(),
            $this->createStub(DependenciasLegado::class),
        );

        $this->assertSame(
            ["motivo" => "sin_tipo_bus"],
            $inferencia->inferir(["id" => 1, "fecha" => "2026-10-20 06:00:00", "tipo_bus_id" => null, "it_tipo_bus_id" => null], 1, 1, new \DateTimeImmutable("2026-10-09")),
        );
    }

    private static function asiento(int $numero, int $fila, int $columna, ?int $id = null): ElementoCroquis
    {
        return new ElementoCroquis(ElementoCroquis::ASIENTO, 1, $fila, $columna, $numero, AsientoClase::A, $id);
    }
}
