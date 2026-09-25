<?php

declare(strict_types=1);

namespace App\Navigation;

use App\Entity\Menu;
use App\Taxonomy\TaxonomyTreeReader;
use App\Taxonomy\TaxonomyTreeWriter;
use App\Taxonomy\TreeNode;

/**
 * Árbol de un menú para el editor: lectura con los ítems serializados y
 * guardado del árbol completo (`[{ id: menuItemId, children: [...] }]`).
 */
final class MenuTree
{
    public function __construct(
        private readonly TaxonomyTreeReader $reader,
        private readonly TaxonomyTreeWriter $writer,
        private readonly MenuItemLoader $items,
    ) {}

    /**
     * @return list<array<string, mixed>> ítems serializados con `children`
     */
    public function read(Menu $menu): array
    {
        $taxonomyId = $menu->getTaxonomy()->getId();

        return self::serialize($this->reader->read([$taxonomyId], $this->items)[$taxonomyId]);
    }

    /**
     * @param mixed $tree `[{ id: int, children?: [...] }]`
     *
     * @throws \App\Taxonomy\InvalidTaxonomyTree
     */
    public function write(Menu $menu, mixed $tree): void
    {
        $this->writer->replace($menu->getTaxonomy(), self::toTaxonomyPayload($tree));
    }

    /**
     * @param list<TreeNode> $nodes
     *
     * @return list<array<string, mixed>>
     */
    public static function serialize(array $nodes): array
    {
        return array_map(
            static fn (TreeNode $node): array => [
                ...MenuItemLoader::serialize($node->subject),
                'children' => self::serialize($node->children),
            ],
            $nodes,
        );
    }

    /** `{ id, children }` del editor → forma genérica `{ subjectId, children }` de la taxonomía. */
    private static function toTaxonomyPayload(mixed $tree): mixed
    {
        if (!is_array($tree)) {
            return $tree;
        }

        return array_map(
            static fn (mixed $node): mixed => is_array($node)
                ? ['subjectId' => $node['id'] ?? null, 'children' => self::toTaxonomyPayload($node['children'] ?? [])]
                : $node,
            $tree,
        );
    }
}
