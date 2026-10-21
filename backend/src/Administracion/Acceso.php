<?php

declare(strict_types=1);

namespace App\Administracion;

use App\Entity\Role;
use App\Entity\Usuario;
use App\Navigation\MenuVisibility;

/**
 * Quién ve una caja o un enlace de la administración (puro): sin roles ni
 * usuarios, cualquiera; si no, el usuario nombrado o el que tenga alguno de
 * los roles o un ascendiente de alguno (`Role.parents`, la misma regla de
 * los menús, `MenuVisibility`). Un enlace se ve solo si también se ve su caja.
 */
final class Acceso
{
    /**
     * @param iterable<Role> $rolesUsuario
     * @param iterable<Role> $roles
     * @param iterable<Usuario> $usuarios
     */
    public static function permite(int $usuarioId, iterable $rolesUsuario, iterable $roles, iterable $usuarios): bool
    {
        $roles = [...$roles];
        $usuarios = [...$usuarios];
        if ($roles === [] && $usuarios === []) {
            return true;
        }
        foreach ($usuarios as $usuario) {
            if ($usuario->getId() === $usuarioId) {
                return true;
            }
        }

        return $roles !== [] && MenuVisibility::isVisibleTo($rolesUsuario, $roles);
    }
}
