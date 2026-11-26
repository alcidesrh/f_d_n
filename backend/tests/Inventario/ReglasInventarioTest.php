<?php

declare(strict_types=1);

namespace App\Tests\Inventario;

use App\Acceso\Capacidad;
use App\Acceso\Ubicacion;
use App\Entity\Almacen;
use App\Entity\Articulo;
use App\Entity\Empresa;
use App\Entity\Enum\TipoAlmacen;
use App\Entity\Estacion;
use App\Entity\Existencia;
use App\Inventario\ReglasInventario as R;
use PHPUnit\Framework\TestCase;

/** Estado de una existencia, cuánto reponer, dónde está un almacén y los textos del faltante (ADR-071). */
final class ReglasInventarioTest extends TestCase
{
    public function testElEstadoVaDeAgotadoABien(): void
    {
        self::assertSame(R::AGOTADO, R::estado(0, 1000, 1500));
        self::assertSame(R::AGOTADO, R::estado(0, 0, null));
        self::assertSame(R::BAJO_MINIMO, R::estado(999, 1000, 1500));
        self::assertSame(R::POR_REPONER, R::estado(1000, 1000, 1500));
        self::assertSame(R::POR_REPONER, R::estado(1500, 1000, 1500));
        self::assertSame(R::BIEN, R::estado(1501, 1000, 1500));
        self::assertSame(R::BIEN, R::estado(1000, 1000, null));
    }

    public function testSoloHayFaltanteConUnMinimoPuesto(): void
    {
        self::assertTrue(R::faltante(999, 1000));
        self::assertFalse(R::faltante(1000, 1000));
        self::assertFalse(R::faltante(0, 0));
    }

    public function testReponeHastaElMaximoOElMayorDeLosNiveles(): void
    {
        self::assertSame(4000, R::reponer(1000, 1000, 1500, 5000));
        self::assertSame(1200, R::reponer(300, 1000, 1500, null));
        self::assertSame(700, R::reponer(300, 1000, null, null));
        self::assertSame(0, R::reponer(2000, 1000, 1500, 5000));
        // Sin niveles no se repone, aunque esté en cero.
        self::assertSame(0, R::reponer(0, 0, null, null));
    }

    public function testCadaCapacidadMiraLasDimensionesQueLeTocan(): void
    {
        $a = self::almacen(TipoAlmacen::ESTACION, 1, 10);

        self::assertEquals(Ubicacion::empresa(1)->conEstacion(10), R::ubicacion(Capacidad::INVENTARIO_MOVER, $a));
        self::assertEquals(Ubicacion::empresa(1), R::ubicacion(Capacidad::INVENTARIO_ARTICULOS, $a));
        // Un taller sin estación: solo quien tiene todas las estaciones de la empresa.
        self::assertEquals(Ubicacion::empresa(1)->conEstacion(null), R::ubicacion(Capacidad::INVENTARIO_MOVER, self::almacen(TipoAlmacen::TALLER, 1, null)));
    }

    public function testLosTextosDelFaltanteYDeLaReposicion(): void
    {
        $e = self::existencia();
        $e->niveles(1000, null, null);
        $e->entrar(200, 10000);

        self::assertSame("existencia:3:7", R::clave($e));
        self::assertSame("Quedan 2.00 rollo de «Papel térmico» en Central; el mínimo es 10.00.", R::avisoFaltante($e));
        $e->sacar(200);
        self::assertSame("No queda nada de «Papel térmico» en Central; el mínimo es 10.00.", R::avisoFaltante($e));
        $e->entrar(1200, 10000);
        self::assertSame("Se repuso: hay 12.00 rollo (mínimo 10.00).", R::avisoRepuesto($e));
        $e->niveles(0, null, null);
        self::assertSame("Ya no tiene mínimo: hay 12.00 rollo.", R::avisoRepuesto($e));
    }

    public function testElFaltanteVaAlTipoDelArticuloODelAlmacen(): void
    {
        self::assertSame("insumo_agotado", TipoAlmacen::ESTACION->tipoAfectacionFaltante());
        self::assertSame("insumo_agotado", TipoAlmacen::OFICINA->tipoAfectacionFaltante());
        self::assertSame("repuesto_faltante", TipoAlmacen::TALLER->tipoAfectacionFaltante());
        self::assertSame("repuesto_faltante", TipoAlmacen::BUS->tipoAfectacionFaltante());
    }

    public static function existencia(): Existencia
    {
        $articulo = (new Articulo())->setNombre("Papel térmico")->setUnidad("rollo");
        $articulo->setId(7);

        return new Existencia(self::almacen(TipoAlmacen::ESTACION, 1, 10, 3), $articulo);
    }

    private static function almacen(TipoAlmacen $tipo, int $empresa, ?int $estacion, int $id = 3): Almacen
    {
        $e = new Empresa();
        (new \ReflectionProperty($e, "id"))->setValue($e, $empresa);
        $a = (new Almacen())->setNombre("Central")->setTipo($tipo)->setEmpresa($e);
        if ($estacion !== null) {
            $s = new Estacion();
            (new \ReflectionProperty($s, "id"))->setValue($s, $estacion);
            $a->setEstacion($s);
        }
        $a->setId($id);

        return $a;
    }
}
