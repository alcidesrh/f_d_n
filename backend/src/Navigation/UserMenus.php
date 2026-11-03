<?php

declare(strict_types=1);

namespace App\Navigation;

use App\Acceso\Autorizacion;
use App\Acceso\PermisosUsuario;
use App\Entity\MenuItem;
use App\Entity\Usuario;
use App\Taxonomy\TaxonomyTreeReader;
use App\Taxonomy\TreeNode;

/**
 * Navegación de un usuario (ADR-049): por área, los menús colocados en ella
 * cuya audiencia lo incluye (`Audiencia`), en su orden, cada uno con los
 * ítems que puede abrir (`Pantallas`). Un grupo sin hijos a la vista no se
 * manda, y un menú sin ítems tampoco. Consultas fijas: placements (+menú
 * +roles), nodos, ítems (+ícono +ruta), las asignaciones del usuario y, si
 * algún ítem es un enlace escrito a mano, las rutas.
 */
final class UserMenus
{
    public function __construct(
        private readonly MenuLayout $layout,
        private readonly TaxonomyTreeReader $reader,
        private readonly MenuItemLoader $items,
        private readonly Autorizacion $autorizacion,
        private readonly Pantallas $pantallas,
    ) {}

    /**
     * @return array<string, list<array{id: int, nombre: string, items: list<array<string, mixed>>}>>
     */
    public function for(Usuario $user): array
    {
        $permisos = $this->autorizacion->de($user);
        $visible = array_values(array_filter(
            $this->layout->placements(),
            static fn ($placement) => Audiencia::incluye($permisos, $placement->getMenu()->getRoles()),
        ));

        $taxonomyIds = array_values(array_unique(array_map(
            static fn ($placement) => $placement->getMenu()->getTaxonomy()->getId(),
            $visible,
        )));
        $forests = array_map(fn (array $forest) => $this->podar($forest, $permisos), $this->reader->read($taxonomyIds, $this->items));

        $result = array_fill_keys(array_keys(MenuLayout::emptyLayout()), []);
        foreach ($visible as $placement) {
            $menu = $placement->getMenu();
            $items = $forests[$menu->getTaxonomy()->getId()];
            if ($items === []) {
                continue;
            }
            $result[$placement->getArea()->value][] = [
                'id' => $menu->getId(),
                'nombre' => $menu->getNombre(),
                'items' => MenuTree::serialize($items),
            ];
        }

        return $result;
    }

    /**
     * Sin los ítems de pantallas que no puede abrir (con todo lo que cuelga
     * de ellos) ni los grupos que se quedan vacíos.
     *
     * @param list<TreeNode> $nodos
     *
     * @return list<TreeNode>
     */
    private function podar(array $nodos, PermisosUsuario $permisos): array
    {
        $resultado = [];
        foreach ($nodos as $nodo) {
            $item = $nodo->subject;
            if (!$item instanceof MenuItem) {
                continue;
            }
            $abre = match (true) {
                $item->getRoute() !== null => $this->pantallas->abre($permisos, $item->getRoute()),
                $item->getPath() !== null => $this->pantallas->abrePath($permisos, $item->getPath()),
                default => null,
            };
            if ($abre === false) {
                continue;
            }
            $hijos = $this->podar($nodo->children, $permisos);
            if ($abre === null && $hijos === []) {
                continue;
            }
            $resultado[] = new TreeNode($nodo->subjectClass, $nodo->subjectId, $hijos, $item);
        }

        return $resultado;
    }
}
