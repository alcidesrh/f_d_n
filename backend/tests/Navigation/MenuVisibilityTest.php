<?php

declare(strict_types=1);

namespace App\Tests\Navigation;

use App\Entity\Role;
use App\Navigation\MenuVisibility;
use PHPUnit\Framework\TestCase;

final class MenuVisibilityTest extends TestCase
{
    private static function role(int $id, ?string $nombre = null): Role
    {
        $role = (new Role())->setNombre($nombre ?? 'ROLE_' . $id);
        $role->setId($id);

        return $role;
    }

    public function testVisibleSiTieneAsignadoUnoDeLosRolesDelMenu(): void
    {
        $this->assertTrue(MenuVisibility::isVisibleTo([9, 2], false, [self::role(2)]));
        $this->assertFalse(MenuVisibility::isVisibleTo([9], false, [self::role(2)]));
    }

    public function testSinJerarquiaUnRolNoHeredaLosMenusDeOtro(): void
    {
        // Antes ROLE_SUPER_ADMIN veía lo de ROLE_ADMIN por ser su ascendiente; ahora no.
        $this->assertFalse(MenuVisibility::isVisibleTo([1], false, [self::role(2)]));
    }

    public function testElRolBaseLoVenTodos(): void
    {
        $this->assertTrue(MenuVisibility::isVisibleTo([], false, [self::role(36, Role::user)]));
    }

    public function testElRolDeSistemaVeTodo(): void
    {
        $this->assertTrue(MenuVisibility::isVisibleTo([], true, [self::role(2)]));
    }

    public function testUnMenuSinRolesNoLoVeNadie(): void
    {
        $this->assertFalse(MenuVisibility::isVisibleTo([1], true, []));
    }
}
