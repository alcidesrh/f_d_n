<?php

declare(strict_types=1);

namespace App\Tests\Acceso;

use App\Acceso\Ambito;
use App\Acceso\Concesion;
use App\Acceso\Legado\ConsolidacionRoles;
use App\Acceso\Legado\EquivalenciasLegado;
use App\Acceso\PermisosUsuario;
use PHPUnit\Framework\TestCase;

final class ConsolidacionRolesTest extends TestCase
{
    /**
     * @param list<string>       $capacidades
     * @param array<string, int> $limites
     */
    private static function concesion(int $id, array $capacidades, Ambito $ambito, array $limites = [], ?int $rol = null): Concesion
    {
        return new Concesion($id, $rol ?? $id, "Rol $id", false, $capacidades, $limites, $ambito);
    }

    public function testSobraLaQueOtraCubreEnTodoSuAmbito(): void
    {
        $ambito = new Ambito([1], [10]);
        $sobran = ConsolidacionRoles::sobrantes([
            self::concesion(1, ['venta.vender', 'boleto.ver'], $ambito),
            self::concesion(2, ['venta.vender', 'boleto.ver', 'boleto.anular'], $ambito),
        ], [1, 2]);

        self::assertSame([1], $sobran);
    }

    public function testNoSobraSiLaOtraRigeEnMenos(): void
    {
        $sobran = ConsolidacionRoles::sobrantes([
            self::concesion(1, ['boleto.ver'], new Ambito([1], [10, 11])),
            self::concesion(2, ['boleto.ver', 'boleto.anular'], new Ambito([1], [10])),
        ], [1, 2]);

        self::assertSame([], $sobran);
    }

    public function testDeDosRepetidasQuedaLaPrimera(): void
    {
        $ambito = new Ambito([1], null);

        self::assertSame([2], ConsolidacionRoles::sobrantes([
            self::concesion(1, ['boleto.chequear'], $ambito),
            self::concesion(2, ['boleto.chequear'], $ambito),
        ], [1, 2]));
    }

    public function testLoQueNoEsTocableSeQueda(): void
    {
        $ambito = new Ambito([1], null);

        self::assertSame([], ConsolidacionRoles::sobrantes([
            self::concesion(1, ['boleto.ver'], $ambito),
            self::concesion(2, ['boleto.ver', 'boleto.anular'], $ambito),
        ], [2]));
    }

    public function testNoSobraSiLaOtraLimitaMas(): void
    {
        $ambito = new Ambito([1], null);

        self::assertSame([], ConsolidacionRoles::sobrantes([
            self::concesion(1, ['venta.cortesia'], $ambito),
            self::concesion(2, ['venta.cortesia', 'boleto.ver'], $ambito, ['cortesias_por_dia' => 5]),
        ], [1]));
    }

    public function testNoSacaAlUsuarioDeUnaAudiencia(): void
    {
        $ambito = new Ambito([1], null);
        $concesiones = [
            self::concesion(1, ['encomienda.ver'], $ambito),
            self::concesion(2, ['encomienda.ver', 'boleto.ver'], $ambito),
        ];

        self::assertSame([], ConsolidacionRoles::sobrantes($concesiones, [1], [[1, 7]]));
        self::assertSame([1], ConsolidacionRoles::sobrantes($concesiones, [1], [[1, 2]]));
    }

    public function testUnRolSinCapacidadesSiempreSobra(): void
    {
        self::assertSame([1], ConsolidacionRoles::sobrantes([self::concesion(1, [], Ambito::total())], [1]));
    }

    public function testDiferenciasVeLoQueSePierdeYLoQueSeGana(): void
    {
        $permisos = static fn(array ...$c) => new PermisosUsuario(array_map(static fn(array $x, int $i) => self::concesion($i + 1, $x[0], $x[1]), $c, array_keys($c)), []);
        $amplio = $permisos([['boleto.ver'], new Ambito([1], null)]);
        $estrecho = $permisos([['boleto.ver'], new Ambito([1], [10])]);

        self::assertSame([], ConsolidacionRoles::diferencias($amplio, $amplio));
        self::assertSame(['boleto.ver'], ConsolidacionRoles::diferencias($amplio, $estrecho));
        self::assertSame(['boleto.ver'], ConsolidacionRoles::diferencias($estrecho, $amplio));
    }

    public function testLosRolesFusionadosTienenPuesto(): void
    {
        self::assertSame('ROLE_CHECK_BOLETO', EquivalenciasLegado::puesto('ROLE_CHECK_BOLETO_MOVIL'));
        self::assertNull(EquivalenciasLegado::puesto(EquivalenciasLegado::SIN_EFECTO));
        self::assertNull(EquivalenciasLegado::puesto(EquivalenciasLegado::BASE));
        foreach (array_unique(EquivalenciasLegado::FUSIONES) as $destino) {
            self::assertArrayHasKey($destino, EquivalenciasLegado::ROLES);
        }
    }
}
