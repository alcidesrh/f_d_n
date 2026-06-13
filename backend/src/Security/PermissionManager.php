<?php

namespace App\Security;

use App\Entity\Action;
use App\Entity\Role;
use App\Entity\Usuario;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class PermissionManager {

    /** @var array<int, array<string, bool>> Cache de acciones por user ID */
    private array $actionCache = [];

    /**
     * Resuelve todas las actions permitidas para un usuario.
     * Evalúa: roles del user → permisos de roles → actions de permisos
     *         + actions directas del user
     *         - actions denegadas del user
     *
     * @return string[] Códigos de acción planos
     */
    public function getEffectiveActions(Usuario $user): array {
        $userId = $user->getId();

        if (isset($this->actionCache[$userId])) {
            return array_keys($this->actionCache[$userId]);
        }

        $actions = [];

        // 1. Roles del usuario (con jerarquía: incluir parent roles recursivamente)
        $roles = $this->getAllParentRoles($user->getUserRoles());

        // 2. Permisos de los roles + permisos directos del usuario (bypass roles)
        $permisos = new ArrayCollection();
        foreach ($roles as $role) {
            foreach ($role->getPermisos() as $p) {
                if (!$permisos->contains($p)) {
                    $permisos->add($p);
                }
            }
        }
        foreach ($user->getPermisos() as $p) {
            if (!$permisos->contains($p)) {
                $permisos->add($p);
            }
        }

        // 3. Actions de los permisos
        foreach ($permisos as $permiso) {
            foreach ($permiso->getActions() as $action) {
                $code = $action->getCodigo();
                if ($code !== null) {
                    $actions[$code] = true;
                }
            }
        }

        // 4. Actions directas del usuario (overrides grant)
        foreach ($user->getDirectActions() as $action) {
            $code = $action->getCodigo();
            if ($code !== null) {
                $actions[$code] = true;
            }
        }

        // 5. Actions denegadas (overrides deny)
        foreach ($user->getDeniedActions() as $action) {
            $code = $action->getCodigo();
            if ($code !== null) {
                unset($actions[$code]);
            }
        }

        $this->actionCache[$userId] = $actions;

        return array_keys($actions);
    }

    public function can(Usuario $user, string $actionCode): bool {
        return in_array($actionCode, $this->getEffectiveActions($user), true);
    }

    public function canAny(Usuario $user, array $actionCodes): bool {
        $effective = $this->getEffectiveActions($user);
        foreach ($actionCodes as $code) {
            if (in_array($code, $effective, true)) {
                return true;
            }
        }
        return false;
    }

    public function canAll(Usuario $user, array $actionCodes): bool {
        $effective = $this->getEffectiveActions($user);
        foreach ($actionCodes as $code) {
            if (!in_array($code, $effective, true)) {
                return false;
            }
        }
        return true;
    }

    public function invalidateCache(Usuario $user): void {
        $userId = $user->getId();
        if ($userId !== null) {
            unset($this->actionCache[$userId]);
        }
    }

    /**
     * Obtiene todos los roles incluyendo padres recursivamente.
     *
     * @param Collection<int, Role> $roles
     * @return Collection<int, Role>
     */
    private function getAllParentRoles(Collection $roles): Collection {
        $all = new ArrayCollection();
        foreach ($roles as $role) {
            $this->collectRoleWithParents($role, $all);
        }
        return $all;
    }

    private function collectRoleWithParents(Role $role, ArrayCollection $collection): void {
        if ($collection->contains($role)) {
            return;
        }
        $collection->add($role);
        foreach ($role->getParents() as $parent) {
            $this->collectRoleWithParents($parent, $collection);
        }
    }
}
