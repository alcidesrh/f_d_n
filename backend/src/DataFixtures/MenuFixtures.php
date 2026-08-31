<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Enum\LayoutArea;
use App\Entity\Menu;
use App\Entity\MenuLayoutAssignment;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class MenuFixtures extends Fixture implements DependentFixtureInterface
{
    public const REF_PREFIX = 'menu-';

    private array $menuConfigs = [
        [
            'nombre' => 'Resumen',
            'label' => 'Resumen',
            'icon' => 'grid',
            'sort' => 1,
            'area' => 'sidebar_left',
            'roles' => ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_OPERADOR', 'ROLE_CONSULTA'],
        ],
        [
            'nombre' => 'Flota',
            'label' => 'Flota de buses',
            'icon' => 'bus',
            'sort' => 2,
            'area' => 'sidebar_left',
            'roles' => ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_OPERADOR'],
        ],
        [
            'nombre' => 'Rutas',
            'label' => 'Rutas',
            'icon' => 'route',
            'sort' => 3,
            'area' => 'sidebar_left',
            'roles' => ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_OPERADOR'],
        ],
        [
            'nombre' => 'Choferes',
            'label' => 'Choferes',
            'icon' => 'users',
            'sort' => 4,
            'area' => 'sidebar_left',
            'roles' => ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_OPERADOR'],
        ],
        [
            'nombre' => 'Boletos',
            'label' => 'Boletos y ventas',
            'icon' => 'ticket',
            'sort' => 5,
            'area' => 'sidebar_left',
            'roles' => ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_OPERADOR'],
        ],
        [
            'nombre' => 'Gestion',
            'label' => 'Gestión',
            'icon' => null,
            'sort' => 10,
            'area' => 'sidebar_left',
            'roles' => ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'],
        ],
        [
            'nombre' => 'Gestion Usuarios',
            'label' => 'Usuarios',
            'icon' => 'users',
            'sort' => 1,
            'area' => 'sidebar_left',
            'parent' => 'Gestion',
            'roles' => ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'],
        ],
        [
            'nombre' => 'Gestion Roles',
            'label' => 'Roles',
            'icon' => 'shield',
            'sort' => 2,
            'area' => 'sidebar_left',
            'parent' => 'Gestion',
            'roles' => ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'],
        ],
        [
            'nombre' => 'Gestion Permisos',
            'label' => 'Permisos',
            'icon' => 'lock',
            'sort' => 3,
            'area' => 'sidebar_left',
            'parent' => 'Gestion',
            'roles' => ['ROLE_SUPER_ADMIN'],
        ],
        [
            'nombre' => 'CRUD Dinamico',
            'label' => 'CRUD dinámico',
            'icon' => 'table',
            'sort' => 4,
            'area' => 'sidebar_left',
            'parent' => 'Gestion',
            'roles' => ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'],
        ],
        [
            'nombre' => 'Ajustes',
            'label' => 'Ajustes',
            'icon' => 'settings',
            'sort' => 5,
            'area' => 'sidebar_left',
            'parent' => 'Gestion',
            'roles' => ['ROLE_SUPER_ADMIN'],
        ],
        [
            'nombre' => 'Reportes',
            'label' => 'Reportes',
            'icon' => 'barchart',
            'sort' => 6,
            'area' => 'sidebar_right',
            'roles' => ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_OPERADOR'],
        ],
        [
            'nombre' => 'Notificaciones',
            'label' => 'Notificaciones',
            'icon' => 'bell',
            'sort' => 1,
            'area' => 'topbar_right',
            'roles' => ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_OPERADOR', 'ROLE_CONSULTA'],
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        $created = [];

        foreach ($this->menuConfigs as $config) {
            $menu = new Menu();
            $menu->setNombre($config['nombre']);
            $menu->setLabel($config['label']);
            $menu->setIcon($config['icon']);
            $menu->setSort($config['sort']);

            $manager->persist($menu);
            $created[$config['nombre']] = $menu;
        }

        $manager->flush();

        foreach ($this->menuConfigs as $config) {
            $menu = $created[$config['nombre']];

            if (isset($config['parent']) && isset($created[$config['parent']])) {
                $menu->addChild($created[$config['parent']]);
            }

            foreach ($config['roles'] as $roleName) {
                $role = $this->getReference(RoleFixtures::REF_PREFIX . $roleName, \App\Entity\Role::class);
                $menu->addAllowRole($role);
            }

            $manager->persist($menu);
        }

        $manager->flush();

        foreach ($this->menuConfigs as $config) {
            $menu = $created[$config['nombre']];
            $areaValue = $config['area'];
            $area = LayoutArea::from($areaValue);

            $assignment = new MenuLayoutAssignment();
            $assignment->setMenu($menu);
            $assignment->setLayoutArea($area);
            $assignment->setPosition($config['sort']);
            $manager->persist($assignment);
        }

        $manager->flush();

        foreach ($created as $name => $menu) {
            $this->addReference(self::REF_PREFIX . $name, $menu);
        }
    }

    public function getDependencies(): array
    {
        return [
            RoleFixtures::class,
        ];
    }
}
