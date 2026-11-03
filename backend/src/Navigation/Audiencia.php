<?php

declare(strict_types=1);

namespace App\Navigation;

use App\Acceso\PermisosUsuario;
use App\Entity\Role;

/**
 * A quién se le muestra un menú, una caja o un enlace de la administración
 * (ADR-049): curaduría, no seguridad. Sin roles, a todos; con roles, a quien
 * tenga una asignación vigente de alguno, y al de sistema siempre. Sin
 * jerarquía: un rol no hereda lo de otro. Lo que de verdad decide si algo se
 * ve es si el usuario puede abrir su pantalla (`Pantallas`): la audiencia
 * solo puede acotarlo, nunca ampliarlo. Puro.
 */
final class Audiencia
{
    /** @param iterable<Role> $roles */
    public static function incluye(PermisosUsuario $permisos, iterable $roles): bool
    {
        $ids = [];
        foreach ($roles as $rol) {
            $ids[] = (int) $rol->getId();
        }

        return $ids === [] || $permisos->sistema() || array_intersect($ids, $permisos->roles()) !== [];
    }
}
