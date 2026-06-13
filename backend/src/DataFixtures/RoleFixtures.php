<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class RoleFixtures extends Fixture
{
    public const REF_PREFIX = 'role-';

    public const ROLES = [
        'ROLE_SUPER_ADMIN' => [
            'nombre' => 'Super Administrador',
            'permisos' => ['Gestion Usuarios', 'Gestion Roles', 'Gestion Permisos', 'Gestion Acciones', 'Gestion Boletos', 'Gestion Rutas'],
        ],
        'ROLE_ADMIN' => [
            'nombre' => 'Administrador',
            'permisos' => ['Gestion Usuarios', 'Gestion Roles', 'Gestion Boletos', 'Gestion Rutas'],
        ],
        'ROLE_OPERADOR' => [
            'nombre' => 'Operador',
            'permisos' => ['Gestion Boletos'],
        ],
        'ROLE_CONSULTA' => [
            'nombre' => 'Consulta',
            'permisos' => [],
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::ROLES as $roleName => $config) {
            $role = new Role();
            $role->setNombre($roleName);

            foreach ($config['permisos'] as $permisoNombre) {
                $permiso = $this->getReference(PermisoFixtures::REF_PREFIX . $permisoNombre, \App\Entity\Permiso::class);
                $role->addPermiso($permiso);
            }

            $manager->persist($role);
            $this->addReference(self::REF_PREFIX . $roleName, $role);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            PermisoFixtures::class,
        ];
    }
}
