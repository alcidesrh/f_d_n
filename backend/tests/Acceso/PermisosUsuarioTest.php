<?php

declare(strict_types=1);

namespace App\Tests\Acceso;

use App\Acceso\Ambito;
use App\Acceso\Concesion;
use App\Acceso\Limite;
use App\Acceso\PermisosUsuario;
use App\Acceso\Ubicacion;
use PHPUnit\Framework\TestCase;

final class PermisosUsuarioTest extends TestCase
{
    private const CATALOGO = ['venta.vender', 'venta.cortesia', 'boleto.ver', 'config.editar'];

    /**
     * @param list<string>       $capacidades
     * @param array<string, int> $limites
     */
    private static function concesion(int $id, array $capacidades, Ambito $ambito, array $limites = [], bool $sistema = false): Concesion
    {
        return new Concesion($id, $id * 10, "Rol $id", $sistema, $capacidades, $limites, $ambito);
    }

    public function testLaUnionDeAsignacionesSinNegaciones(): void
    {
        $p = new PermisosUsuario([
            self::concesion(1, ['venta.vender'], new Ambito([1], [10])),
            self::concesion(2, ['venta.vender', 'boleto.ver'], new Ambito([2], [10])),
        ], self::CATALOGO);

        self::assertTrue($p->puede('venta.vender', Ubicacion::empresa(2)->conEstacion(10)));
        self::assertFalse($p->puede('boleto.ver', Ubicacion::empresa(1)->conEstacion(10)));
        self::assertTrue($p->puede('boleto.ver'));
        self::assertFalse($p->puede('config.editar'));
        self::assertSame(2, $p->origen('venta.vender', Ubicacion::empresa(2))?->asignacion);
        self::assertSame(['boleto.ver', 'venta.vender'], array_keys($p->capacidades()));
    }

    public function testElRolDeSistemaTieneTodoElCatalogoSinLimites(): void
    {
        $p = new PermisosUsuario([self::concesion(1, [], Ambito::total(), [], true)], self::CATALOGO);

        self::assertTrue($p->sistema());
        self::assertTrue($p->puede('config.editar', Ubicacion::empresa(null)));
        self::assertNull($p->limite('venta.cortesia', Limite::CORTESIAS_POR_DIA));
        self::assertSame(self::CATALOGO, array_values(array_intersect(self::CATALOGO, array_keys($p->capacidades()))));
    }

    public function testRigeElLimiteMasPermisivo(): void
    {
        $p = new PermisosUsuario([
            self::concesion(1, ['venta.cortesia'], new Ambito([1], null), ['cortesias_por_dia' => 2]),
            self::concesion(2, ['venta.cortesia'], new Ambito([1, 2], null), ['cortesias_por_dia' => 5]),
        ], self::CATALOGO);

        self::assertSame(5, $p->limite('venta.cortesia', Limite::CORTESIAS_POR_DIA, Ubicacion::empresa(1)));
        self::assertSame(5, $p->limite('venta.cortesia', Limite::CORTESIAS_POR_DIA, Ubicacion::empresa(2)));
    }

    public function testUnaAsignacionSinLimiteLoQuita(): void
    {
        $p = new PermisosUsuario([
            self::concesion(1, ['venta.cortesia'], new Ambito([1], null), ['cortesias_por_dia' => 2]),
            self::concesion(2, ['venta.cortesia'], new Ambito([1], null)),
        ], self::CATALOGO);

        self::assertNull($p->limite('venta.cortesia', Limite::CORTESIAS_POR_DIA, Ubicacion::empresa(1)));
    }

    public function testLosAmbitosVaciosNoDanNada(): void
    {
        $p = new PermisosUsuario([self::concesion(1, ['venta.vender'], new Ambito([], null))], self::CATALOGO);

        self::assertFalse($p->tiene('venta.vender'));
        self::assertSame([], $p->capacidades());
        self::assertNull($p->origen('venta.vender'));
    }

    public function testElPisoGeneralUneTodosLosRoles(): void
    {
        $p = new PermisosUsuario([
            self::concesion(1, ['venta.vender'], new Ambito([1], [10])),
            self::concesion(2, ['boleto.ver'], new Ambito([3], [10])),
        ], self::CATALOGO);

        self::assertSame([1, 3], $p->general()->empresas());
        self::assertSame([10], $p->general()->estaciones());
        self::assertSame([10, 20], $p->roles());
    }

    public function testLaHuellaCambiaConElAmbito(): void
    {
        $a = new PermisosUsuario([self::concesion(1, ['venta.vender'], new Ambito([1], [10]))], self::CATALOGO);
        $b = new PermisosUsuario([self::concesion(1, ['venta.vender'], new Ambito([1], [11]))], self::CATALOGO);

        self::assertNotSame($a->huella(), $b->huella());
    }
}
