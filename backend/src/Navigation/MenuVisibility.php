<?php

declare(strict_types=1);

namespace App\Navigation;

use App\Entity\Role;

/**
 * Regla de visibilidad de un menú por roles: un usuario lo ve si alguno de
 * sus roles es uno de los roles del menú o un ascendiente (`Role.parents`,
 * transitivo) de alguno de ellos. Un menú sin roles no lo ve nadie.
 */
final class MenuVisibility
{
    /**
     * @param iterable<Role> $userRoles
     * @param iterable<Role> $menuRoles
     */
    public static function isVisibleTo(iterable $userRoles, iterable $menuRoles): bool
    {
        $userRoleIds = [];
        foreach ($userRoles as $role) {
            $userRoleIds[$role->getId()] = true;
        }
        if ($userRoleIds === []) {
            return false;
        }

        foreach ($menuRoles as $role) {
            foreach (self::lineage($role) as $id => $_) {
                if (isset($userRoleIds[$id])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * El rol y todos sus ascendientes, por id (tolera ciclos).
     *
     * @return array<int, Role>
     */
    public static function lineage(Role $role): array
    {
        $lineage = [];
        $pending = [$role];
        while ($pending !== []) {
            $current = array_pop($pending);
            if (isset($lineage[$current->getId()])) {
                continue;
            }
            $lineage[$current->getId()] = $current;
            foreach ($current->getParents() as $parent) {
                $pending[] = $parent;
            }
        }

        return $lineage;
    }
}
