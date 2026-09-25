<?php

declare(strict_types=1);

namespace App\Taxonomy;

/**
 * Operaciones puras sobre árboles de taxonomía (sin Doctrine): armar el árbol
 * desde las filas planas de `taxonomy_node`, podar sujetos inexistentes y
 * validar el árbol que envía un cliente.
 */
final class TaxonomyTree
{
    /**
     * Arma el bosque desde filas de adyacencia. Hermanos ordenados por
     * `position` y luego `id`; una fila cuyo padre no está se trata como raíz.
     *
     * @param iterable<array{id: int, parent: ?int, position: int, subjectClass: string, subjectId: int}> $rows
     *
     * @return list<TreeNode>
     */
    public static function fromRows(iterable $rows): array
    {
        $sorted = [];
        foreach ($rows as $row) {
            $sorted[] = $row;
        }
        usort($sorted, static fn (array $a, array $b): int => [$a['position'], $a['id']] <=> [$b['position'], $b['id']]);

        $nodes = [];
        foreach ($sorted as $row) {
            $nodes[$row['id']] = new TreeNode($row['subjectClass'], (int) $row['subjectId']);
        }

        $roots = [];
        foreach ($sorted as $row) {
            $node = $nodes[$row['id']];
            $parent = $row['parent'] !== null ? ($nodes[$row['parent']] ?? null) : null;
            if ($parent !== null) {
                $parent->children[] = $node;
            } else {
                $roots[] = $node;
            }
        }

        return $roots;
    }

    /**
     * Quita los nodos que no cumplen `$keep` y sube sus hijos (ya podados) a
     * su lugar, preservando el orden.
     *
     * @param list<TreeNode>            $nodes
     * @param callable(TreeNode): bool  $keep
     *
     * @return list<TreeNode>
     */
    public static function prune(array $nodes, callable $keep): array
    {
        $result = [];
        foreach ($nodes as $node) {
            $children = self::prune($node->children, $keep);
            if ($keep($node)) {
                $node->children = $children;
                $result[] = $node;
            } else {
                array_push($result, ...$children);
            }
        }

        return $result;
    }

    /**
     * Valida y convierte el árbol enviado por un cliente:
     * `[{ subjectClass?: string, subjectId: int, children?: [...] }, ...]`.
     * El orden del array es el orden entre hermanos.
     *
     * @param mixed       $payload
     * @param string|null $subjectClass clase obligatoria (y por defecto) de los nodos; null = libre
     * @param int|null    $maxDepth     profundidad máxima (0 = solo raíces); null = sin límite
     *
     * @return list<TreeNode>
     */
    public static function parse(mixed $payload, ?string $subjectClass = null, ?int $maxDepth = null): array
    {
        $seen = [];

        return self::parseLevel($payload, $subjectClass, $maxDepth, 0, '', $seen);
    }

    /**
     * @param array<string, true> $seen
     *
     * @return list<TreeNode>
     */
    private static function parseLevel(mixed $items, ?string $subjectClass, ?int $maxDepth, int $depth, string $path, array &$seen): array
    {
        if (!is_array($items) || !array_is_list($items)) {
            throw new InvalidTaxonomyTree(sprintf('Se esperaba una lista de nodos en "%s".', $path ?: 'raíz'));
        }
        if ($items !== [] && $maxDepth !== null && $depth > $maxDepth) {
            throw new InvalidTaxonomyTree(sprintf('Profundidad máxima %d superada en "%s".', $maxDepth, $path));
        }

        $nodes = [];
        foreach ($items as $index => $item) {
            $at = $path . '/' . $index;
            if (!is_array($item)) {
                throw new InvalidTaxonomyTree(sprintf('Nodo inválido en "%s".', $at));
            }

            $class = $item['subjectClass'] ?? $subjectClass;
            if (!is_string($class) || $class === '') {
                throw new InvalidTaxonomyTree(sprintf('Falta subjectClass en "%s".', $at));
            }
            if ($subjectClass !== null && $class !== $subjectClass) {
                throw new InvalidTaxonomyTree(sprintf('La taxonomía solo admite %s ("%s" es %s).', $subjectClass, $at, $class));
            }

            $id = $item['subjectId'] ?? null;
            if (!is_int($id) || $id <= 0) {
                throw new InvalidTaxonomyTree(sprintf('subjectId inválido en "%s".', $at));
            }

            $key = TreeNode::keyOf($class, $id);
            if (isset($seen[$key])) {
                throw new InvalidTaxonomyTree(sprintf('%s aparece más de una vez.', $key));
            }
            $seen[$key] = true;

            $children = self::parseLevel($item['children'] ?? [], $subjectClass, $maxDepth, $depth + 1, $at, $seen);
            $nodes[] = new TreeNode($class, $id, $children);
        }

        return $nodes;
    }

    /**
     * Recorre el árbol en profundidad (padre antes que hijos).
     *
     * @param list<TreeNode> $nodes
     *
     * @return \Generator<int, array{node: TreeNode, parent: ?TreeNode, position: int}>
     */
    public static function walk(array $nodes, ?TreeNode $parent = null): \Generator
    {
        foreach ($nodes as $position => $node) {
            yield ['node' => $node, 'parent' => $parent, 'position' => $position];
            yield from self::walk($node->children, $node);
        }
    }
}
