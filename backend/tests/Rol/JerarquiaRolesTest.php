<?php

declare(strict_types=1);

namespace App\Tests\Rol;

use App\Entity\Role;
use App\Rol\JerarquiaInvalida;
use App\Rol\JerarquiaRoles;
use PHPUnit\Framework\TestCase;

final class JerarquiaRolesTest extends TestCase
{
    private static function rol(int $id, string $nombre, Role ...$padres): Role
    {
        $rol = (new Role())->setNombre($nombre);
        $rol->setId($id);
        foreach ($padres as $padre) {
            $rol->addParent($padre);
        }

        return $rol;
    }

    public function testElBosqueSoloTraeRolesConParientesYOrdenaPorNombre(): void
    {
        $super = self::rol(1, 'ROLE_SUPER_ADMIN');
        $admin = self::rol(2, 'ROLE_ADMIN', $super);
        $user = self::rol(36, 'ROLE_USER', $admin);
        $agencia = self::rol(13, 'ROLE_AGENCIA', $super);
        $suelto = self::rol(20, 'ROLE_EMISOR_CORTESIA');

        $this->assertSame(
            [['id' => 1, 'children' => [
                ['id' => 2, 'children' => [['id' => 36, 'children' => []]]],
                ['id' => 13, 'children' => []],
            ]]],
            JerarquiaRoles::bosque([$user, $suelto, $agencia, $admin, $super]),
        );
    }

    public function testConVariosPadresCuelgaSoloDelDeMenorId(): void
    {
        $a = self::rol(1, 'A');
        $b = self::rol(2, 'B');
        $c = self::rol(3, 'C', $b, $a);

        $this->assertSame(
            [['id' => 1, 'children' => [['id' => 3, 'children' => []]]]],
            JerarquiaRoles::bosque([$a, $b, $c]),
        );
    }

    public function testUnCicloNoSeRepiteNiSePierde(): void
    {
        $a = self::rol(1, 'A');
        $b = self::rol(2, 'B', $a);
        $a->addParent($b);

        $this->assertSame([['id' => 1, 'children' => [['id' => 2, 'children' => []]]]], JerarquiaRoles::bosque([$a, $b]));
    }

    public function testAplanaElArbolEnPadres(): void
    {
        $this->assertSame(
            [1 => null, 2 => 1, 36 => 2, 13 => 1],
            JerarquiaRoles::padres([['id' => 1, 'children' => [['id' => 2, 'children' => [['id' => 36]]], ['id' => 13, 'children' => []]]]]),
        );
    }

    public function testRechazaUnRolRepetido(): void
    {
        $this->expectException(JerarquiaInvalida::class);
        JerarquiaRoles::padres([['id' => 1, 'children' => [['id' => 2]]], ['id' => 2]]);
    }

    public function testRechazaNodosSinId(): void
    {
        $this->expectException(JerarquiaInvalida::class);
        JerarquiaRoles::padres([['nombre' => 'x']]);
    }
}
