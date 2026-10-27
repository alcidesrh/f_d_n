<?php

declare(strict_types=1);

namespace App\ConfiguracionListado;

use App\Entity\ListConfiguration;
use App\Entity\Role;

/**
 * Reglas de las configuraciones del listado de una entidad (puras, ADR-043):
 *
 * - Hay exactamente una por defecto: la que no tiene roles. No se elimina ni
 *   se le asocian roles mientras sea la única.
 * - Un rol está a lo sumo en una configuración por entidad.
 * - El rol base (`ROLE_USER`) no se asocia: lo tienen todos, para eso está
 *   la por defecto.
 * - A un usuario le toca la primera (por `position`) con alguno de sus
 *   roles; si ninguna, la por defecto.
 */
final class Reglas
{
    /**
     * @param iterable<ListConfiguration> $configuraciones las de la entidad
     * @param list<int>                   $rolesUsuario    ids de los roles con asignación vigente
     */
    public static function para(iterable $configuraciones, array $rolesUsuario): ?ListConfiguration
    {
        $ordenadas = self::ordenadas($configuraciones);
        foreach ($ordenadas as $config) {
            if (array_intersect($config->rolIds(), $rolesUsuario) !== []) {
                return $config;
            }
        }
        foreach ($ordenadas as $config) {
            if ($config->esPorDefecto()) {
                return $config;
            }
        }

        return $ordenadas[0] ?? null;
    }

    /**
     * Por qué no se pueden dar estos roles a `$editada` (null: una nueva);
     * null si se puede.
     *
     * @param iterable<ListConfiguration> $configuraciones las de la entidad
     * @param list<Role>                  $roles
     */
    public static function rechazoRoles(iterable $configuraciones, ?ListConfiguration $editada, array $roles): ?string
    {
        foreach ($roles as $rol) {
            if ($rol->esBase()) {
                return sprintf("El rol «%s» lo tienen todos: para ellos está la configuración por defecto.", $rol->getNombre());
            }
        }
        $otras = array_values(array_filter(self::ordenadas($configuraciones), static fn(ListConfiguration $c) => $c !== $editada));
        foreach ($roles as $rol) {
            foreach ($otras as $otra) {
                if (\in_array($rol->getId(), $otra->rolIds(), true)) {
                    return sprintf("El rol «%s» ya está en la configuración «%s»: un rol solo puede estar en una por entidad.", $rol->getNombre(), $otra->getNombre());
                }
            }
        }
        $otraPorDefecto = array_values(array_filter($otras, static fn(ListConfiguration $c) => $c->esPorDefecto()))[0] ?? null;
        if ($roles === [] && $otraPorDefecto !== null) {
            return sprintf("La configuración por defecto es «%s»: asocia al menos un rol a esta.", $otraPorDefecto->getNombre());
        }
        if ($roles !== [] && $otraPorDefecto === null && $editada?->esPorDefecto()) {
            return "Es la configuración por defecto: la ven los usuarios sin un rol asociado a otra y no puede tener roles.";
        }

        return null;
    }

    public static function rechazoEliminar(ListConfiguration $config): ?string
    {
        return $config->esPorDefecto() ? "La configuración por defecto no se elimina." : null;
    }

    /**
     * @param iterable<ListConfiguration> $configuraciones
     *
     * @return list<ListConfiguration>
     */
    public static function ordenadas(iterable $configuraciones): array
    {
        $lista = [...$configuraciones];
        usort($lista, static fn(ListConfiguration $a, ListConfiguration $b) => [$a->getPosition(), $a->getId() ?? PHP_INT_MAX] <=> [$b->getPosition(), $b->getId() ?? PHP_INT_MAX]);

        return $lista;
    }
}
