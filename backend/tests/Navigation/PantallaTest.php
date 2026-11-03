<?php

declare(strict_types=1);

namespace App\Tests\Navigation;

use App\Acceso\Ambito;
use App\Acceso\Catalogo\CatalogoRecursos;
use App\Acceso\Concesion;
use App\Acceso\PermisosUsuario;
use App\Entity\ApiToken;
use App\Entity\Bus;
use App\Entity\BusClase;
use App\Entity\Role;
use App\Navigation\Audiencia;
use App\Navigation\Pantalla;
use App\Navigation\PatronRuta;
use App\Services\VueRouteSynchronizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Qué pantallas puede abrir un usuario y a quién se le muestra cada menú o
 * caja (ADR-049): la regla de la pantalla es la del router del frontend
 * (`puedeEntrar`), y la audiencia por roles solo acota.
 */
final class PantallaTest extends TestCase
{
    private const CATALOGO = ['venta.vender', 'boleto.ver', 'config.editar', 'bus.ver', 'bus.editar', 'bus.eliminar'];

    private static function permisos(array $capacidades, bool $sistema = false, int $rol = 7): PermisosUsuario
    {
        return new PermisosUsuario([new Concesion(1, $rol, "Rol $rol", $sistema, $capacidades, [], Ambito::total())], self::CATALOGO);
    }

    private static function recursos(): CatalogoRecursos
    {
        return CatalogoRecursos::desdeClases([Bus::class, BusClase::class, ApiToken::class]);
    }

    private static function lista(): Pantalla
    {
        return new Pantalla('entity-list', '/lista/:entity?', [], VueRouteSynchronizer::recurso(['parametro' => 'entity', 'operacion' => 'ver', 'selector' => 'config.editar']));
    }

    private static function formulario(): Pantalla
    {
        return new Pantalla('entity-form', '/form/:entity/:id?', [], VueRouteSynchronizer::recurso(['parametro' => 'entity', 'operacion' => 'editar', 'registro' => 'id']));
    }

    public function testSinCapacidadLaAbreCualquieraConSesion(): void
    {
        $this->assertTrue((new Pantalla('ayuda', '/ayuda/:guia?'))->admite(self::permisos([]), self::recursos()));
    }

    public function testExigeAlgunaDeSusCapacidades(): void
    {
        $chequeo = new Pantalla('salidas-chequeo', '/salidas/chequeo', ['boleto.chequear', 'venta.vender']);
        $this->assertTrue($chequeo->admite(self::permisos(['venta.vender']), self::recursos()));
        $this->assertFalse($chequeo->admite(self::permisos(['boleto.ver']), self::recursos()));
        $this->assertTrue($chequeo->admite(self::permisos([], sistema: true), self::recursos()), 'el de sistema tiene lo del catálogo');
    }

    public function testUnaCapacidadQueNoExisteNoLaAbreNadie(): void
    {
        $this->assertFalse((new Pantalla('x', '/x', ['venta.vendr']))->admite(self::permisos([], sistema: true), self::recursos()));
    }

    public function testElListadoGenericoExigeVerLaEntidad(): void
    {
        $this->assertTrue(self::lista()->admite(self::permisos(['bus.ver']), self::recursos(), ['entity' => 'bus']));
        $this->assertFalse(self::lista()->admite(self::permisos(['boleto.ver']), self::recursos(), ['entity' => 'bus']));
        $this->assertTrue(self::lista()->admite(self::permisos([]), self::recursos(), ['entity' => 'bus-clase']), 'de lectura libre');
        $this->assertFalse(self::lista()->admite(self::permisos([], sistema: true), self::recursos(), ['entity' => 'api-token']), 'lo oculto no lo abre nadie');
        $this->assertFalse(self::lista()->admite(self::permisos([], sistema: true), self::recursos(), ['entity' => 'no-existe']));
    }

    public function testSinEntidadEsElSelectorYExigeSuCapacidad(): void
    {
        $this->assertFalse(self::lista()->admite(self::permisos(['bus.ver']), self::recursos()));
        $this->assertTrue(self::lista()->admite(self::permisos(['config.editar']), self::recursos()));
    }

    public function testElFormularioCreaConEditarYAbreUnoExistenteConVer(): void
    {
        $verBus = self::permisos(['bus.ver']);
        $this->assertFalse(self::formulario()->admite($verBus, self::recursos(), ['entity' => 'bus']), 'crear exige editar');
        $this->assertTrue(self::formulario()->admite($verBus, self::recursos(), ['entity' => 'bus', 'id' => '5']));
        $this->assertTrue(self::formulario()->admite(self::permisos(['bus.editar']), self::recursos(), ['entity' => 'bus']));
        $this->assertFalse(self::formulario()->admite(self::permisos([]), self::recursos(), ['entity' => 'bus-clase']), 'leer libre no es poder crear');
    }

    public function testDeParametroAEntidad(): void
    {
        $this->assertSame('BusClase', Pantalla::entidad('bus-clase'));
        $this->assertSame('TarifaEncomiendaPeso', Pantalla::entidad('tarifa_encomienda-peso'));
    }

    /** @param array<string, string>|null $esperado */
    #[DataProvider('paths')]
    public function testPatronDeRuta(string $patron, string $path, ?array $esperado): void
    {
        $this->assertSame($esperado, PatronRuta::de($patron)->comparar(PatronRuta::sinConsulta($path)));
    }

    public static function paths(): iterable
    {
        yield 'fija' => ['/salidas/chequeo', '/salidas/chequeo', []];
        yield 'fija con barra final y mayúsculas' => ['/salidas/chequeo', '/Salidas/Chequeo/', []];
        yield 'otra fija' => ['/salidas', '/salidas/chequeo', null];
        yield 'opcional ausente' => ['/lista/:entity?', '/lista', []];
        yield 'opcional presente' => ['/lista/:entity?', '/lista/bus-clase?orden=1#x', ['entity' => 'bus-clase']];
        yield 'dos parámetros' => ['/form/:entity/:id?', '/form/bus/12', ['entity' => 'bus', 'id' => '12']];
        yield 'obligatorio ausente' => ['/form/:entity/:id?', '/form', null];
        yield 'con restricción' => ['/rutas/:id(nueva|\d+)', '/rutas/nueva', ['id' => 'nueva']];
        yield 'restricción no cumplida' => ['/rutas/:id(nueva|\d+)', '/rutas/otra', null];
        yield 'restricción con signo' => ['/configuracion/usuarios/:id(-?\d+)/accesos', '/configuracion/usuarios/-1/accesos', ['id' => '-1']];
        yield 'opcional con restricción' => ['/chat/:canal(\d+)?', '/chat', []];
        yield 'codificado' => ['/ayuda/:guia?', '/ayuda/venta%20en%20linea', ['guia' => 'venta en linea']];
        yield 'raíz' => ['/', '/', []];
        yield 'el comodín no es de nadie' => ['/:pathMatch(.*)*', '/cualquier/cosa', null];
    }

    public function testLosTramosFijosGananALosParametros(): void
    {
        $this->assertGreaterThan(PatronRuta::de('/rutas/:id(nueva|\d+)')->puntaje, PatronRuta::de('/salidas/calendario')->puntaje);
        $this->assertGreaterThan(PatronRuta::de('/salidas/:id')->puntaje, PatronRuta::de('/salidas/chequeo')->puntaje);
        $this->assertTrue(PatronRuta::de('/:pathMatch(.*)*')->comodin);
    }

    public function testLaSincronizacionNormalizaLoQueExigeCadaRuta(): void
    {
        $this->assertSame(['venta.vender'], VueRouteSynchronizer::capacidades('venta.vender'));
        $this->assertSame(['a.b', 'c.d'], VueRouteSynchronizer::capacidades(['a.b', ' c.d ', 'a.b', 3, '']));
        $this->assertSame([], VueRouteSynchronizer::capacidades(null));
        $this->assertSame(['parametro' => 'entity', 'operacion' => 'editar', 'registro' => 'id'], VueRouteSynchronizer::recurso(['parametro' => 'entity', 'operacion' => 'editar', 'registro' => 'id', 'otra' => 1]));
        $this->assertNull(VueRouteSynchronizer::recurso(['parametro' => 'entity', 'operacion' => 'borrar']));
        $this->assertNull(VueRouteSynchronizer::recurso(['parametro' => 'en tity', 'operacion' => 'ver']));
        $this->assertNull(VueRouteSynchronizer::recurso('entity'));
    }

    public function testUnLoteSinExigenciasEsDeLaVersionAnteriorYNoLasBorra(): void
    {
        $viejo = [['name' => 'inicio', 'path' => '/'], ['name' => 'config', 'path' => '/configuracion', 'children' => [['name' => 'menus', 'path' => 'menus']]]];
        $this->assertFalse(VueRouteSynchronizer::declaraExigencias($viejo));
        $this->assertFalse(VueRouteSynchronizer::declaraExigencias([]));

        $this->assertTrue(VueRouteSynchronizer::declaraExigencias([...$viejo, ['name' => 'venta', 'path' => '/venta', 'capacidad' => ['venta.vender']]]));
        $this->assertTrue(VueRouteSynchronizer::declaraExigencias([['name' => 'config', 'path' => '/configuracion', 'children' => [['name' => 'lista', 'path' => 'lista/:entity?', 'recurso' => ['parametro' => 'entity', 'operacion' => 'ver']]]]]));
    }

    public function testLaAudienciaSoloAcota(): void
    {
        $rol = (new Role())->setNombre('ROLE_GERENTE')->setId(2);
        $this->assertTrue(Audiencia::incluye(self::permisos([], rol: 9), []), 'sin roles, a todos');
        $this->assertTrue(Audiencia::incluye(self::permisos([], rol: 2), [$rol]));
        $this->assertFalse(Audiencia::incluye(self::permisos([], rol: 9), [$rol]), 'sin jerarquía: otro rol no hereda');
        $this->assertFalse(Audiencia::incluye(PermisosUsuario::ninguno(), [$rol]));
        $this->assertTrue(Audiencia::incluye(self::permisos([], sistema: true, rol: 1), [$rol]), 'el de sistema, siempre');
    }
}
