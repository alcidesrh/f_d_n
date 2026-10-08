<?php

declare(strict_types=1);

namespace App\Tests\Chat;

use App\Chat\Directorio;
use App\Chat\Perfil;
use PHPUnit\Framework\TestCase;

final class DirectorioTest extends TestCase
{
    private static function admin(int $id): Perfil
    {
        return new Perfil($id, "Admin $id");
    }

    private static function estacion(int $id, int $estacion = 1): Perfil
    {
        return new Perfil($id, "Estación $id", estacion: $estacion);
    }

    private static function agencia(int $id, int $agencia = 1): Perfil
    {
        return new Perfil($id, "Agencia $id", agencia: $agencia);
    }

    public function testElAmbitoSaleDeAgenciaYEstacion(): void
    {
        $this->assertSame(Perfil::ADMINISTRACION, self::admin(1)->ambito());
        $this->assertSame(Perfil::ESTACION, self::estacion(1)->ambito());
        $this->assertSame(Perfil::AGENCIA, self::agencia(1)->ambito());
    }

    public function testNadieConversaConsigoMismo(): void
    {
        $this->assertFalse(Directorio::puedenConversar(self::admin(1), self::admin(1)));
    }

    public function testAdministracionYEstacionesConversanEntreTodas(): void
    {
        $this->assertTrue(Directorio::puedenConversar(self::admin(1), self::estacion(2)));
        $this->assertTrue(Directorio::puedenConversar(self::estacion(1, 1), self::estacion(2, 7)));
        $this->assertTrue(Directorio::puedenConversar(self::admin(1), self::admin(2)));
    }

    public function testUnaAgenciaSoloHablaConLaAdministracionYConSuGente(): void
    {
        $this->assertTrue(Directorio::puedenConversar(self::agencia(1), self::admin(2)));
        $this->assertTrue(Directorio::puedenConversar(self::admin(2), self::agencia(1)));
        $this->assertTrue(Directorio::puedenConversar(self::agencia(1, 5), self::agencia(2, 5)));
        $this->assertFalse(Directorio::puedenConversar(self::agencia(1, 5), self::agencia(2, 6)));
        $this->assertFalse(Directorio::puedenConversar(self::agencia(1), self::estacion(2)));
        $this->assertFalse(Directorio::puedenConversar(self::estacion(2), self::agencia(1)));
    }

    public function testUnGrupoNoPuedeJuntarAgenciasConEstaciones(): void
    {
        $this->assertNull(Directorio::parIncompatible([self::admin(1), self::estacion(2), self::estacion(3)]));
        $this->assertNull(Directorio::parIncompatible([self::admin(1), self::agencia(2), self::agencia(3)]));

        $par = Directorio::parIncompatible([self::admin(1), self::agencia(2), self::estacion(3)]);
        $this->assertSame([2, 3], [$par[0]->id, $par[1]->id]);
    }
}
