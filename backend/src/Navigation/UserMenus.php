<?php

declare(strict_types=1);

namespace App\Navigation;

use App\Entity\Usuario;
use App\Taxonomy\TaxonomyTreeReader;

/**
 * Navegación de un usuario: por área, los menús colocados en ella que puede
 * ver (`MenuVisibility`), en su orden, cada uno con su árbol de ítems.
 * Consultas fijas: placements (+menú +roles), nodos, ítems (+ícono +ruta);
 * los ascendientes de roles se cargan de forma diferida (grafo pequeño).
 */
final class UserMenus
{
    public function __construct(
        private readonly MenuLayout $layout,
        private readonly TaxonomyTreeReader $reader,
        private readonly MenuItemLoader $items,
    ) {}

    /**
     * @return array<string, list<array{id: int, nombre: string, items: list<array<string, mixed>>}>>
     */
    public function for(Usuario $user): array
    {
        $visible = array_values(array_filter(
            $this->layout->placements(),
            static fn ($placement) => MenuVisibility::isVisibleTo($user->getUserRoles(), $placement->getMenu()->getRoles()),
        ));

        $taxonomyIds = array_values(array_unique(array_map(
            static fn ($placement) => $placement->getMenu()->getTaxonomy()->getId(),
            $visible,
        )));
        $forests = $this->reader->read($taxonomyIds, $this->items);

        $result = array_fill_keys(array_keys(MenuLayout::emptyLayout()), []);
        foreach ($visible as $placement) {
            $menu = $placement->getMenu();
            $result[$placement->getArea()->value][] = [
                'id' => $menu->getId(),
                'nombre' => $menu->getNombre(),
                'items' => MenuTree::serialize($forests[$menu->getTaxonomy()->getId()]),
            ];
        }

        return $result;
    }
}
