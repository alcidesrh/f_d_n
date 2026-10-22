<?php

declare(strict_types=1);

namespace App\Tests\Acceso;

use App\Acceso\Ambito;
use App\Acceso\Concesion;
use App\Acceso\Delegacion;
use App\Acceso\PermisosUsuario;
use PHPUnit\Framework\TestCase;

final class DelegacionTest extends TestCase
{
    private const CATALOGO = ['iam.asignar', 'venta.vender', 'boleto.anular', 'config.editar'];

    private static function nombre(): \Closure
    {
        return static fn(string $c) => $c;
    }

    /** @param list<array{0: list<string>, 1: Ambito, 2?: bool}> $concesiones */
    private static function permisos(array $concesiones): PermisosUsuario
    {
        $lista = [];
        foreach ($concesiones as $i => $c) {
            $lista[] = new Concesion($i + 1, $i + 1, 'Rol ' . ($i + 1), $c[2] ?? false, $c[0], [], $c[1]);
        }

        return new PermisosUsuario($lista, self::CATALOGO);
    }

    public function testOtorgaLoQueTieneDondeLoTiene(): void
    {
        $actor = self::permisos([[['iam.asignar', 'venta.vender'], new Ambito([1], [10, 11])]]);

        self::assertNull(Delegacion::rechazoOtorgar($actor, 1, 2, ['venta.vender'], false, new Ambito([1], [10]), self::nombre()));
    }

    public function testNoOtorgaFueraDeSuAlcance(): void
    {
        $actor = self::permisos([[['iam.asignar', 'venta.vender'], new Ambito([1], [10])]]);

        self::assertNotNull(Delegacion::rechazoOtorgar($actor, 1, 2, ['venta.vender'], false, new Ambito([1, 2], [10]), self::nombre()));
        self::assertNotNull(Delegacion::rechazoOtorgar($actor, 1, 2, ['venta.vender'], false, new Ambito([1], null), self::nombre()));
    }

    public function testNoOtorgaLoQueNoTiene(): void
    {
        $actor = self::permisos([[['iam.asignar', 'venta.vender'], new Ambito([1], [10])]]);

        $motivo = Delegacion::rechazoOtorgar($actor, 1, 2, ['venta.vender', 'boleto.anular'], false, new Ambito([1], [10]), self::nombre());

        self::assertStringContainsString('boleto.anular', (string) $motivo);
    }

    public function testNoSeAsignaASiMismo(): void
    {
        $actor = self::permisos([[[], Ambito::total(), true]]);

        self::assertNotNull(Delegacion::rechazoOtorgar($actor, 1, 1, ['venta.vender'], false, new Ambito([1], [10]), self::nombre()));
    }

    public function testElRolDeSistemaSoloLoOtorgaQuienLoTiene(): void
    {
        $admin = self::permisos([[self::CATALOGO, Ambito::total()]]);
        $sistema = self::permisos([[[], Ambito::total(), true]]);

        self::assertNotNull(Delegacion::rechazoOtorgar($admin, 1, 2, [], true, Ambito::total(), self::nombre()));
        self::assertNull(Delegacion::rechazoOtorgar($sistema, 1, 2, [], true, Ambito::total(), self::nombre()));
    }

    public function testSobreOtroUsuarioHaceFaltaDominarlo(): void
    {
        $actor = self::permisos([[['iam.asignar', 'venta.vender'], new Ambito([1], null)]]);
        $vendedor = [new Concesion(9, 5, 'Vendedor', false, ['venta.vender'], [], new Ambito([1], [10]))];
        $supervisor = [new Concesion(9, 6, 'Supervisor', false, ['boleto.anular'], [], new Ambito([1], [10]))];
        $administrador = [new Concesion(9, 7, 'Sistema', true, [], [], Ambito::total())];

        self::assertNull(Delegacion::rechazoSobreUsuario($actor, 1, 2, $vendedor, self::nombre()));
        self::assertStringContainsString('Supervisor', (string) Delegacion::rechazoSobreUsuario($actor, 1, 2, $supervisor, self::nombre()));
        self::assertNotNull(Delegacion::rechazoSobreUsuario($actor, 1, 2, $administrador, self::nombre()));
        // Sobre sí mismo (Mi cuenta) no aplica.
        self::assertNull(Delegacion::rechazoSobreUsuario($actor, 2, 2, $supervisor, self::nombre()));
    }

    public function testAgregarAUnRolAsignadoExigeTenerloDondeEstaAsignado(): void
    {
        $actor = self::permisos([[['venta.vender', 'boleto.anular'], new Ambito([1], null)]]);

        self::assertNull(Delegacion::rechazoEditarRol($actor, ['venta.vender'], ['venta.vender', 'boleto.anular'], [], [], [new Ambito([1], [10])], self::nombre()));
        self::assertNotNull(Delegacion::rechazoEditarRol($actor, ['venta.vender'], ['venta.vender', 'boleto.anular'], [], [], [new Ambito([2], [10])], self::nombre()));
        self::assertNotNull(Delegacion::rechazoEditarRol($actor, [], ['config.editar'], [], [], [], self::nombre()));
        // Quitar siempre se puede.
        self::assertNull(Delegacion::rechazoEditarRol($actor, ['config.editar'], [], [], [], [new Ambito([2], null)], self::nombre()));
    }

    public function testAflojarUnLimiteEsComoAgregarLaCapacidad(): void
    {
        $actor = self::permisos([[['venta.cortesia'], new Ambito([1], null)]]);
        $asignado = [new Ambito([2], null)];

        self::assertNotNull(Delegacion::rechazoEditarRol($actor, ['venta.cortesia'], ['venta.cortesia'], ['cortesias_por_dia' => 2], ['cortesias_por_dia' => 5], $asignado, self::nombre()));
        self::assertNotNull(Delegacion::rechazoEditarRol($actor, ['venta.cortesia'], ['venta.cortesia'], ['cortesias_por_dia' => 2], [], $asignado, self::nombre()));
        self::assertNull(Delegacion::rechazoEditarRol($actor, ['venta.cortesia'], ['venta.cortesia'], ['cortesias_por_dia' => 5], ['cortesias_por_dia' => 2], $asignado, self::nombre()));
    }
}
