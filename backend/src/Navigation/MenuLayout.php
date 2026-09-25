<?php

declare(strict_types=1);

namespace App\Navigation;

use App\Entity\Enum\LayoutArea;
use App\Entity\Menu;
use App\Entity\MenuPlacement;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Qué menús se despliegan en cada área de la UI y en qué orden:
 * `{ "sidebar_left": [menuId, ...], "sidebar_right": [...], "topbar_right": [...] }`.
 */
final class MenuLayout
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * Placements ordenados por área y posición, con menú y roles ya cargados.
     *
     * @return list<MenuPlacement>
     */
    public function placements(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('p', 'm', 'r')
            ->from(MenuPlacement::class, 'p')
            ->join('p.menu', 'm')
            ->leftJoin('m.roles', 'r')
            ->orderBy('p.area')
            ->addOrderBy('p.position')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<string, list<int>> ids de menú por área (todas las áreas)
     */
    public function get(): array
    {
        $layout = self::emptyLayout();
        foreach ($this->placements() as $placement) {
            $layout[$placement->getArea()->value][] = $placement->getMenu()->getId();
        }

        return $layout;
    }

    /**
     * Reemplaza el contenido de las áreas presentes en `$layout`; las demás
     * quedan como estaban.
     *
     * @throws InvalidMenuLayout si un área, un menú o un duplicado no es válido
     */
    public function replace(mixed $layout): void
    {
        if (!is_array($layout)) {
            throw new InvalidMenuLayout('Se esperaba { área: [menuId, ...] }.');
        }

        $requested = [];
        foreach ($layout as $areaValue => $menuIds) {
            $area = LayoutArea::tryFrom((string) $areaValue)
                ?? throw new InvalidMenuLayout(sprintf('Área desconocida "%s".', $areaValue));
            if (!is_array($menuIds) || !array_is_list($menuIds) || array_filter($menuIds, static fn ($id) => !is_int($id)) !== []) {
                throw new InvalidMenuLayout(sprintf('El área "%s" espera una lista de ids de menú.', $area->value));
            }
            if (count($menuIds) !== count(array_unique($menuIds))) {
                throw new InvalidMenuLayout(sprintf('Un menú aparece dos veces en "%s".', $area->value));
            }
            $requested[$area->value] = $menuIds;
        }

        $menus = $this->menusById(array_merge([], ...array_values($requested)));

        $existing = [];
        foreach ($this->placements() as $placement) {
            $existing[$placement->getArea()->value][$placement->getMenu()->getId()] = $placement;
        }

        foreach ($requested as $areaValue => $menuIds) {
            $current = $existing[$areaValue] ?? [];
            foreach ($menuIds as $position => $menuId) {
                $placement = $current[$menuId] ?? null;
                if ($placement !== null) {
                    $placement->setPosition($position);
                    unset($current[$menuId]);
                } else {
                    $this->entityManager->persist(new MenuPlacement($menus[$menuId], LayoutArea::from($areaValue), $position));
                }
            }
            foreach ($current as $removed) {
                $this->entityManager->remove($removed);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * @return array<string, list<int>>
     */
    public static function emptyLayout(): array
    {
        return array_fill_keys(array_map(static fn (LayoutArea $area) => $area->value, LayoutArea::cases()), []);
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, Menu>
     */
    private function menusById(array $ids): array
    {
        $byId = [];
        foreach ($this->entityManager->getRepository(Menu::class)->findBy(['id' => array_values(array_unique($ids))]) as $menu) {
            $byId[$menu->getId()] = $menu;
        }

        $missing = array_diff($ids, array_keys($byId));
        if ($missing !== []) {
            throw new InvalidMenuLayout(sprintf('No existen menús con id %s.', implode(', ', array_unique($missing))));
        }

        return $byId;
    }
}
