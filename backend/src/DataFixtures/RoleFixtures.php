<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Acceso\Capacidad;
use App\Entity\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/** Roles de ejemplo del modelo de ADR-039: el de sistema, el base y dos puestos. */
class RoleFixtures extends Fixture
{
    public const REF_PREFIX = 'role-';

    public const SISTEMA = 'ROLE_SUPER_ADMIN';
    public const VENDEDOR = 'ROLE_VENDEDOR_BOLETOS';
    public const SUPERVISOR = 'ROLE_SUPERVISOR_BOLETO';

    public function load(ObjectManager $manager): void
    {
        $roles = [
            self::SISTEMA => (new Role())->setNombre(self::SISTEMA)->setDescripcion('Administración total del sistema')->setSistema(true),
            Role::user => (new Role())->setNombre(Role::user)->setDescripcion('Todos los usuarios (menús y administración)'),
            self::VENDEDOR => (new Role())->setNombre(self::VENDEDOR)->setDescripcion('Vende boletos en las estaciones')
                ->setCapacidades([Capacidad::VENTA_VENDER->value, Capacidad::BOLETO_VER->value]),
            self::SUPERVISOR => (new Role())->setNombre(self::SUPERVISOR)->setDescripcion('Responsable de la venta de boletos en las estaciones')
                ->setCapacidades([
                    Capacidad::VENTA_VENDER->value,
                    Capacidad::VENTA_CORTESIA->value,
                    Capacidad::BOLETO_VER->value,
                    Capacidad::BOLETO_ANULAR->value,
                    Capacidad::BOLETO_REASIGNAR->value,
                    Capacidad::BOLETO_CANCELAR->value,
                ])
                ->setLimites(['cortesias_por_dia' => 5]),
        ];
        foreach ($roles as $nombre => $role) {
            $manager->persist($role);
            $this->addReference(self::REF_PREFIX . $nombre, $role);
        }

        $manager->flush();
    }
}
