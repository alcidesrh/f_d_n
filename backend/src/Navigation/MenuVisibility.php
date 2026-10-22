<?php

declare(strict_types=1);

namespace App\Navigation;

use App\Entity\Role;

/**
 * Regla de visibilidad de un menú por roles (ADR-018, ADR-039): un usuario
 * lo ve si tiene una asignación vigente de alguno de sus roles, si el menú
 * lleva el rol base (`ROLE_USER`: lo ven todos) o si tiene el rol de
 * sistema. Un menú sin roles no lo ve nadie. Ya no hay jerarquía de roles:
 * un rol no hereda los menús de otro.
 *
 * Los ítems de cada menú los filtra el frontend por la capacidad que exige
 * su pantalla; la que decide al final es la API.
 */
final class MenuVisibility
{
    /**
     * @param list<int>      $rolesUsuario ids de los roles con asignación vigente
     * @param iterable<Role> $menuRoles
     */
    public static function isVisibleTo(array $rolesUsuario, bool $sistema, iterable $menuRoles): bool
    {
        $menuRoles = [...$menuRoles];
        if ($menuRoles === []) {
            return false;
        }
        if ($sistema) {
            return true;
        }
        foreach ($menuRoles as $role) {
            if ($role->esBase() || in_array($role->getId(), $rolesUsuario, true)) {
                return true;
            }
        }

        return false;
    }
}
