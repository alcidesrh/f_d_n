<?php

declare(strict_types=1);

namespace App\Tests\Croquis;

use App\Croquis\Croquis;
use App\Croquis\ElementoCroquis;
use App\Croquis\InvalidCroquis;
use App\Entity\Enum\AsientoClase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CroquisTest extends TestCase
{
    private static function asiento(int $numero, int $fila, int $columna, string $clase = 'A', int $planta = 1, ?int $id = null): array
    {
        return ['tipo' => 'asiento', 'id' => $id, 'numero' => $numero, 'clase' => $clase, 'planta' => $planta, 'fila' => $fila, 'columna' => $columna];
    }

    public function testDesdeLegadoPasaDeMultiplosDe50AConsecutivasDesde1(): void
    {
        $this->assertSame([1, 2, 3, 4, 5], array_map(Croquis::desdeLegado(...), [0, 50, 100, 150, 200]));
        $this->assertSame(3, Croquis::desdeLegado('100'));
        $this->assertSame(1, Croquis::desdeLegado(null));
    }

    public function testDesdeArrayLeeAsientosYSenales(): void
    {
        $elementos = Croquis::desdeArray(['elementos' => [
            self::asiento(1, 2, 1, 'B', 1, 10),
            ['tipo' => 'chofer', 'planta' => 1, 'fila' => 1, 'columna' => 1],
            ['tipo' => 'puerta', 'id' => 4, 'planta' => 1, 'fila' => 1, 'columna' => 5],
        ]]);

        $this->assertCount(3, $elementos);
        $this->assertTrue($elementos[0]->esAsiento());
        $this->assertSame(AsientoClase::B, $elementos[0]->clase);
        $this->assertSame(10, $elementos[0]->id);
        $this->assertSame('chofer', $elementos[1]->tipo);
        $this->assertNull($elementos[1]->numero);
        $this->assertSame(4, $elementos[2]->id);
    }

    public static function invalidos(): iterable
    {
        yield 'sin elementos' => [[], 'Se esperaba'];
        yield 'tipo desconocido' => [['elementos' => [['tipo' => 'baño', 'planta' => 1, 'fila' => 1, 'columna' => 1]]], 'tipo desconocido'];
        yield 'planta 3' => [['elementos' => [self::asiento(1, 1, 1, 'A', 3)]], '`planta`'];
        yield 'fila 0' => [['elementos' => [self::asiento(1, 0, 1)]], '`fila`'];
        yield 'columna fuera del marco' => [['elementos' => [self::asiento(1, 1, Croquis::COLUMNAS_MAX + 1)]], '`columna`'];
        yield 'coordenada no entera' => [['elementos' => [['tipo' => 'puerta', 'planta' => 1, 'fila' => '2', 'columna' => 1]]], '`fila`'];
        yield 'clase C' => [['elementos' => [self::asiento(1, 1, 1, 'C')]], 'clase'];
        yield 'sin número' => [['elementos' => [['tipo' => 'asiento', 'clase' => 'A', 'planta' => 1, 'fila' => 1, 'columna' => 1]]], '`numero`'];
        yield 'celda ocupada dos veces' => [['elementos' => [self::asiento(1, 1, 1), ['tipo' => 'puerta', 'planta' => 1, 'fila' => 1, 'columna' => 1]]], 'Dos elementos'];
        yield 'número repetido' => [['elementos' => [self::asiento(7, 1, 1), self::asiento(7, 1, 2)]], 'repetido'];
        yield 'dos choferes' => [['elementos' => [
            ['tipo' => 'chofer', 'planta' => 1, 'fila' => 1, 'columna' => 1],
            ['tipo' => 'chofer', 'planta' => 2, 'fila' => 1, 'columna' => 1],
        ]], 'un chofer'];
        yield 'id repetido' => [['elementos' => [self::asiento(1, 1, 1, 'A', 1, 3), self::asiento(2, 1, 2, 'A', 1, 3)]], 'dos veces'];
    }

    #[DataProvider('invalidos')]
    public function testDesdeArrayRechaza(mixed $payload, string $mensaje): void
    {
        $this->expectException(InvalidCroquis::class);
        $this->expectExceptionMessage($mensaje);
        Croquis::desdeArray($payload);
    }

    public function testElMismoNumeroEnPlantasDistintasSigueSiendoRepetido(): void
    {
        $this->expectException(InvalidCroquis::class);
        Croquis::desdeArray(['elementos' => [self::asiento(1, 1, 1, 'B', 1), self::asiento(1, 1, 1, 'A', 2)]]);
    }

    public function testLaMismaCeldaEnPlantasDistintasEsValida(): void
    {
        $this->assertCount(2, Croquis::desdeArray(['elementos' => [self::asiento(1, 1, 1, 'B', 1), self::asiento(2, 1, 1, 'A', 2)]]));
    }

    public function testOrdenarEsPorPlantaFilaColumna(): void
    {
        $e = static fn (int $p, int $f, int $c) => new ElementoCroquis('puerta', $p, $f, $c);
        $ordenados = Croquis::ordenar([$e(2, 1, 1), $e(1, 3, 1), $e(1, 1, 4), $e(1, 1, 2)]);

        $this->assertSame(['1:1:2', '1:1:4', '1:3:1', '2:1:1'], array_map(static fn (ElementoCroquis $x) => $x->celda(), $ordenados));
    }

    public function testFirmaIgnoraIdsYOrdenPeroNoLaDistribucion(): void
    {
        $a = [new ElementoCroquis('asiento', 1, 1, 1, 1, AsientoClase::A, 10), new ElementoCroquis('chofer', 1, 1, 5, id: 3)];
        $b = [new ElementoCroquis('chofer', 1, 1, 5), new ElementoCroquis('asiento', 1, 1, 1, 1, AsientoClase::A, 99)];
        $c = [new ElementoCroquis('asiento', 1, 1, 1, 1, AsientoClase::B), new ElementoCroquis('chofer', 1, 1, 5)];

        $this->assertSame(Croquis::firma($a), Croquis::firma($b));
        $this->assertNotSame(Croquis::firma($a), Croquis::firma($c));
    }
}
