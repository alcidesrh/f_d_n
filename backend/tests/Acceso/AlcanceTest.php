<?php

declare(strict_types=1);

namespace App\Tests\Acceso;

use App\Acceso\Alcance;
use App\Acceso\Ambito;
use App\Acceso\Ubicacion;
use PHPUnit\Framework\TestCase;

final class AlcanceTest extends TestCase
{
    public function testUnaEmpresaYEstacionContieneSoloEsaCelda(): void
    {
        $a = new Ambito([1], [10]);

        self::assertTrue($a->contiene(Ubicacion::empresa(1)->conEstacion(10)));
        self::assertFalse($a->contiene(Ubicacion::empresa(2)->conEstacion(10)));
        self::assertFalse($a->contiene(Ubicacion::empresa(1)->conEstacion(11)));
        // La estación no aplica: basta la empresa.
        self::assertTrue($a->contiene(Ubicacion::empresa(1)));
    }

    public function testCompartidoYSinEstacionSoloLosCubreTodas(): void
    {
        self::assertFalse((new Ambito([1], null))->contiene(Ubicacion::empresa(null)));
        self::assertTrue((new Ambito(null, [10]))->contiene(Ubicacion::empresa(null)));
        self::assertFalse((new Ambito(null, [10]))->contiene(Ubicacion::empresa(1)->conEstacion(null)));
        self::assertTrue((new Ambito([1], null))->contiene(Ubicacion::empresa(1)->conEstacion(null)));
    }

    public function testSinDimensionesLaCubreCualquierAmbito(): void
    {
        self::assertTrue((new Ambito([1], [10]))->contiene(Ubicacion::ninguna()));
    }

    public function testElUsuarioDeAgenciaSoloOperaLoDeSuAgencia(): void
    {
        $agencia = new Ambito(null, null, 7);

        self::assertTrue($agencia->contiene(Ubicacion::empresa(1)->conAgencia(7)));
        self::assertFalse($agencia->contiene(Ubicacion::empresa(1)->conAgencia(8)));
        // Una venta de estación no es de su agencia.
        self::assertFalse($agencia->contiene(Ubicacion::empresa(1)->conAgencia(null)));
        // Quien no es de agencia ve las de cualquiera.
        self::assertTrue((new Ambito([1], null))->contiene(Ubicacion::empresa(1)->conAgencia(8)));
    }

    public function testLaUnionNoEsElProducto(): void
    {
        $alcance = new Alcance([new Ambito([1], [10]), new Ambito([2], [20])]);

        self::assertTrue($alcance->contiene(Ubicacion::empresa(1)->conEstacion(10)));
        self::assertTrue($alcance->contiene(Ubicacion::empresa(2)->conEstacion(20)));
        self::assertFalse($alcance->contiene(Ubicacion::empresa(1)->conEstacion(20)));
        self::assertSame([1, 2], $alcance->empresas());
        self::assertSame([10, 20], $alcance->estaciones());
    }

    public function testLosAmbitosVaciosNoCuentan(): void
    {
        $alcance = new Alcance([new Ambito([], [10]), new Ambito([1], [])]);

        self::assertTrue($alcance->vacio());
        self::assertSame([], $alcance->empresas());
    }

    public function testTodasEnUnaDimension(): void
    {
        $alcance = new Alcance([new Ambito([1], [10]), new Ambito(null, [20])]);

        self::assertNull($alcance->empresas());
        self::assertSame(1, (new Alcance([new Ambito([1], null)]))->empresaUnica());
        self::assertNull($alcance->empresaUnica());
    }

    public function testCubrePorCeldas(): void
    {
        $actor = new Alcance([new Ambito([1, 2], [10]), new Ambito([1], [20])]);

        self::assertTrue($actor->cubre(new Ambito([1], [10, 20])));
        self::assertTrue($actor->cubre(new Ambito([2], [10])));
        self::assertFalse($actor->cubre(new Ambito([2], [10, 20])));
        self::assertFalse($actor->cubre(new Ambito([1], null)));
    }

    public function testCubreConTodasEnUnaDimension(): void
    {
        $actor = new Alcance([new Ambito([1], null), new Ambito([2], null)]);

        self::assertTrue($actor->cubre(new Ambito([1, 2], null)));
        self::assertTrue($actor->cubre(new Ambito([1, 2], [10])));
        self::assertFalse($actor->cubre(new Ambito(null, [10])));
        self::assertFalse($actor->cubre(Ambito::total()));
        self::assertTrue(Alcance::total()->cubre(Ambito::total()));
    }

    public function testCubreUnaAgenciaSoloQuienNoEsDeOtra(): void
    {
        $destino = new Ambito(null, null, 7);

        self::assertTrue(Alcance::total()->cubre($destino));
        self::assertTrue((new Alcance([new Ambito(null, null, 7)]))->cubre($destino));
        self::assertFalse((new Alcance([new Ambito(null, null, 8)]))->cubre($destino));
        self::assertFalse((new Alcance([new Ambito([1], null)]))->cubre($destino));
    }

    public function testCondicionSqlEsUnOrDeAmbitos(): void
    {
        $alcance = new Alcance([new Ambito([1], [10]), new Ambito([2], null)]);

        [$sql, $params] = $alcance->condicion('s.empresa_id', 'v.estacion_id');

        self::assertSame('((s.empresa_id IN (:alcance_e0) AND v.estacion_id IN (:alcance_s0)) OR (s.empresa_id IN (:alcance_e1)))', $sql);
        self::assertSame(['alcance_e0' => [1], 'alcance_s0' => [10], 'alcance_e1' => [2]], $params);
    }

    public function testCondicionSinRestriccionYVacia(): void
    {
        self::assertSame(['1 = 1', []], Alcance::total()->condicion('e'));
        self::assertSame(['1 = 1', []], (new Alcance([new Ambito(null, [10])]))->condicion('e'));
        self::assertSame(['1 = 0', []], Alcance::nada()->condicion('e'));
    }

    public function testCondicionConCompartidos(): void
    {
        [$sql] = (new Alcance([new Ambito([3], null)]))->condicion('t.empresa', null, true, 't');

        self::assertSame('(((t.empresa IN (:t_e0) OR t.empresa IS NULL)))', $sql);
    }
}
