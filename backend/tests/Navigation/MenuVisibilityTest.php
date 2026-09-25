<?php

declare(strict_types=1);

namespace App\Tests\Navigation;

use App\Entity\Role;
use App\Navigation\MenuVisibility;
use PHPUnit\Framework\TestCase;

final class MenuVisibilityTest extends TestCase
{
    private static function role(int $id, Role ...$parents): Role
    {
        $role = (new Role())->setNombre('ROLE_' . $id);
        $role->setId($id);
        foreach ($parents as $parent) {
            $role->addParent($parent);
        }

        return $role;
    }

    public function testVisibleSiElUsuarioTieneUnoDeLosRolesDelMenu(): void
    {
        $operador = self::role(2);

        $this->assertTrue(MenuVisibility::isVisibleTo([self::role(9), $operador], [$operador]));
    }

    public function testVisibleSiUnRolDelUsuarioEsAscendienteDeUnRolDelMenu(): void
    {
        $admin = self::role(1);
        $operador = self::role(2, $admin);
        $consulta = self::role(3, $operador);

        // admin → operador → consulta: admin es abuelo de consulta.
        $this->assertTrue(MenuVisibility::isVisibleTo([$admin], [$consulta]));
    }

    public function testNoVisibleSiElRolDelUsuarioEsDescendiente(): void
    {
        $admin = self::role(1);
        $consulta = self::role(3, $admin);

        $this->assertFalse(MenuVisibility::isVisibleTo([$consulta], [$admin]));
    }

    public function testMenuSinRolesOUsuarioSinRolesNoEsVisible(): void
    {
        $this->assertFalse(MenuVisibility::isVisibleTo([self::role(1)], []));
        $this->assertFalse(MenuVisibility::isVisibleTo([], [self::role(1)]));
    }

    public function testLineageToleraCiclos(): void
    {
        $a = self::role(1);
        $b = self::role(2, $a);
        $a->addParent($b);

        $ids = array_keys(MenuVisibility::lineage($a));
        sort($ids);
        $this->assertSame([1, 2], $ids);
        $this->assertFalse(MenuVisibility::isVisibleTo([self::role(7)], [$a]));
    }
}
