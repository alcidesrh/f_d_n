<?php

declare(strict_types=1);

namespace App\Rol;

use App\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Jerarquía de roles como árbol (`/configuracion/roles`): cada rol cuelga de
 * su padre (`Role.parents`), que ve todo lo que ve el hijo (menús, cajas de
 * `/administracion`; `App\Navigation\MenuVisibility`). Los hijos de un nodo
 * son los roles que incluye: `ROLE_SUPER_ADMIN > ROLE_ADMIN > ROLE_USER`.
 *
 * El árbol solo admite un padre por rol. Si la base trae varios (editados por
 * el CRUD), se muestra bajo el de menor id y guardar deja solo ese. Los roles
 * que no aparecen en el árbol quedan sin padres ni hijos. El orden entre
 * hermanos no significa nada: se devuelven por nombre.
 */
final class JerarquiaRoles
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /** @return list<array{id: int, children: list<mixed>}> */
    public function leer(): array
    {
        return self::bosque($this->roles());
    }

    /**
     * Reemplaza la jerarquía completa por `[{ id, children: [...] }]`.
     *
     * @throws JerarquiaInvalida
     */
    public function escribir(mixed $arbol): void
    {
        $padres = self::padres($arbol);
        $roles = [];
        foreach ($this->roles() as $rol) {
            $roles[$rol->getId()] = $rol;
        }

        $faltan = array_diff(array_keys($padres), array_keys($roles));
        if ($faltan !== []) {
            throw new JerarquiaInvalida(sprintf('No existen roles con id %s.', implode(', ', $faltan)));
        }

        foreach ($roles as $rol) {
            foreach ($rol->getParents()->toArray() as $padre) {
                $rol->removeParent($padre);
            }
        }
        foreach ($padres as $id => $padreId) {
            if ($padreId !== null) {
                $roles[$id]->addParent($roles[$padreId]);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Roles con padres o hijos, cada uno bajo su padre de menor id; raíces
     * los que no tienen padre (o cuyo único camino cierra un ciclo).
     *
     * @param iterable<Role> $roles
     *
     * @return list<array{id: int, children: list<mixed>}>
     */
    public static function bosque(iterable $roles): array
    {
        $porNombre = [...$roles];
        usort($porNombre, static fn (Role $a, Role $b) => strcmp((string) $a->getNombre(), (string) $b->getNombre()));

        $padreDe = [];
        $hijosDe = [];
        foreach ($porNombre as $rol) {
            $ids = array_map(static fn (Role $padre) => $padre->getId(), $rol->getParents()->toArray());
            if ($ids !== []) {
                $padreDe[$rol->getId()] = min($ids);
                $hijosDe[min($ids)][] = $rol->getId();
            }
        }

        $visitados = [];
        $nodo = static function (int $id) use (&$nodo, &$visitados, $hijosDe): ?array {
            if (isset($visitados[$id])) {
                return null;
            }
            $visitados[$id] = true;

            return ['id' => $id, 'children' => array_values(array_filter(array_map($nodo, $hijosDe[$id] ?? [])))];
        };

        $bosque = [];
        foreach ($porNombre as $rol) {
            $id = $rol->getId();
            if (!isset($padreDe[$id]) && isset($hijosDe[$id])) {
                $bosque[] = $nodo($id);
            }
        }
        // Lo que quedó sin raíz es un ciclo: entra por su primer rol.
        foreach ($porNombre as $rol) {
            if (isset($padreDe[$rol->getId()]) && !isset($visitados[$rol->getId()])) {
                $bosque[] = $nodo($rol->getId());
            }
        }

        return $bosque;
    }

    /**
     * Valida el árbol y lo aplana a `[rolId => padreId|null]`.
     *
     * @return array<int, int|null>
     *
     * @throws JerarquiaInvalida
     */
    public static function padres(mixed $arbol, ?int $padre = null, array &$padres = []): array
    {
        if (!is_array($arbol) || !array_is_list($arbol)) {
            throw new JerarquiaInvalida('Se esperaba [{ id, children: [...] }].');
        }
        foreach ($arbol as $nodo) {
            $id = is_array($nodo) ? ($nodo['id'] ?? null) : null;
            if (!is_int($id)) {
                throw new JerarquiaInvalida('Cada nodo necesita un id numérico.');
            }
            if (array_key_exists($id, $padres)) {
                throw new JerarquiaInvalida(sprintf('El rol %d aparece dos veces.', $id));
            }
            $padres[$id] = $padre;
            self::padres($nodo['children'] ?? [], $id, $padres);
        }

        return $padres;
    }

    /** @return list<Role> con sus padres cargados */
    private function roles(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('r', 'p')
            ->from(Role::class, 'r')
            ->leftJoin('r.parents', 'p')
            ->getQuery()
            ->getResult();
    }
}
