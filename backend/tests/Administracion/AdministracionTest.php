<?php

declare(strict_types=1);

namespace App\Tests\Administracion;

use App\Administracion\Acceso;
use App\Administracion\AdministracionRechazada;
use App\Administracion\Datos;
use App\Administracion\Destino;
use App\Entity\Role;
use App\Entity\Usuario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdministracionTest extends TestCase
{
    private static function rol(int $id, Role ...$padres): Role
    {
        $rol = (new Role())->setId($id);
        foreach ($padres as $padre) {
            $rol->addParent($padre);
        }

        return $rol;
    }

    private static function usuario(int $id): Usuario
    {
        return (new Usuario())->setId($id);
    }

    public function testSinRestriccionesLoVeCualquiera(): void
    {
        $this->assertTrue(Acceso::permite(5, [], [], []));
    }

    public function testElUsuarioNombradoLoVeAunqueNoTengaElRol(): void
    {
        $this->assertTrue(Acceso::permite(5, [], [self::rol(1)], [self::usuario(5)]));
        $this->assertFalse(Acceso::permite(6, [], [self::rol(1)], [self::usuario(5)]));
    }

    public function testElRolOUnAscendienteLoDejaVer(): void
    {
        $jefe = self::rol(1);
        $cajero = self::rol(2, $jefe);
        $this->assertTrue(Acceso::permite(5, [$cajero], [$cajero], []));
        $this->assertTrue(Acceso::permite(5, [$jefe], [$cajero], []), 'el ascendiente ve lo del hijo');
        $this->assertFalse(Acceso::permite(5, [$cajero], [$jefe], []), 'el hijo no ve lo del ascendiente');
        $this->assertFalse(Acceso::permite(5, [], [$cajero], []));
    }

    public function testDestinoPorRutaConParametros(): void
    {
        $d = Destino::desde(['ruta' => 'entity-list', 'parametros' => ['entity' => ' bus ']]);
        $this->assertSame('entity-list', $d?->ruta);
        $this->assertSame(['entity' => 'bus'], $d->parametros);
        $this->assertNull($d->path);
    }

    public function testDestinoPorPath(): void
    {
        $this->assertSame('/reporte/ventas?dias=7', Destino::desde(['path' => ' /reporte/ventas?dias=7 '])?->path);
        $this->assertSame('https://sat.gob.gt', Destino::desde(['path' => 'https://sat.gob.gt'])?->path);
        $this->assertNull(Destino::desde(null));
        $this->assertNull(Destino::desde(['ruta' => '', 'path' => '']));
    }

    #[DataProvider('destinosInvalidos')]
    public function testDestinosInvalidos(array $datos, string $codigo): void
    {
        try {
            Destino::desde($datos);
            $this->fail('debía rechazarse');
        } catch (AdministracionRechazada $e) {
            $this->assertSame($codigo, $e->codigo);
        }
    }

    public static function destinosInvalidos(): iterable
    {
        yield 'path sin barra' => [['path' => 'lista/bus'], 'path_invalido'];
        yield 'protocolo relativo' => [['path' => '//otro.com'], 'path_invalido'];
        yield 'javascript' => [['path' => 'javascript:alert(1)'], 'path_invalido'];
        yield 'con espacios' => [['path' => '/lista/bus x'], 'path_invalido'];
        yield 'ruta rara' => [['ruta' => 'a b'], 'ruta_invalida'];
        yield 'parámetro vacío' => [['ruta' => 'entity-list', 'parametros' => ['entity' => '']], 'parametro_invalido'];
        yield 'nombre de parámetro' => [['ruta' => 'entity-list', 'parametros' => ['1x' => 'bus']], 'parametro_invalido'];
    }

    public function testDatosDeUnEnlace(): void
    {
        $this->assertSame('Buses de la flota', Datos::texto(['t' => "  Buses   de la\nflota "], 't', 'el texto'));
        $this->assertSame(['Unidad', 'Carro'], Datos::claves(['claves' => ['Unidad', ' unidad ', '', 'Carro', 5]]));
        $this->assertSame('directions-bus-outline', Datos::icono(['icono' => 'directions-bus-outline']));
        $this->assertSame(['roles' => [1, 2], 'usuarios' => [-1]], Datos::acceso(['acceso' => ['roles' => [1, '2', 1, 'x'], 'usuarios' => ['-1']]]));
        $this->assertNull(Datos::acceso([]));
        $this->expectException(AdministracionRechazada::class);
        Datos::icono(['icono' => 'Bus Grande']);
    }

    public function testElOrdenTieneQueNombrarTodoUnaSolaVez(): void
    {
        $orden = Datos::orden(['secciones' => [['id' => 2, 'enlaces' => [5]], ['id' => 1, 'enlaces' => [4, 3]]]], [1, 2], [3, 4, 5]);
        $this->assertSame([['id' => 2, 'enlaces' => [5]], ['id' => 1, 'enlaces' => [4, 3]]], $orden);

        try {
            Datos::orden(['secciones' => [['id' => 1, 'enlaces' => [3, 4]], ['id' => 2, 'enlaces' => [4]]]], [1, 2], [3, 4, 5]);
            $this->fail('un enlace repetido debía rechazarse');
        } catch (AdministracionRechazada $e) {
            $this->assertSame(['orden_desactualizado', 409], [$e->codigo, $e->estadoHttp]);
        }
    }
}
