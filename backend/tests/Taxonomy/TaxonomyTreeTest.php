<?php

declare(strict_types=1);

namespace App\Tests\Taxonomy;

use App\Taxonomy\InvalidTaxonomyTree;
use App\Taxonomy\TaxonomyTree;
use App\Taxonomy\TreeNode;
use PHPUnit\Framework\TestCase;

final class TaxonomyTreeTest extends TestCase
{
    private static function row(int $id, ?int $parent, int $position, int $subjectId): array
    {
        return ['id' => $id, 'parent' => $parent, 'position' => $position, 'subjectClass' => 'MenuItem', 'subjectId' => $subjectId];
    }

    /** @param list<TreeNode> $nodes */
    private static function shape(array $nodes): array
    {
        return array_map(static fn (TreeNode $n) => [$n->subjectId => self::shape($n->children)], $nodes);
    }

    public function testFromRowsArmaElBosqueOrdenadoPorPositionYLuegoId(): void
    {
        $tree = TaxonomyTree::fromRows([
            self::row(5, null, 1, 50),
            self::row(1, null, 0, 10),
            self::row(3, 1, 1, 30),
            self::row(2, 1, 0, 20),
            self::row(4, 1, 0, 40), // empata con 2 en position: desempata el id
            self::row(6, 3, 0, 60),
        ]);

        $this->assertSame([[10 => [[20 => []], [40 => []], [30 => [[60 => []]]]]], [50 => []]], self::shape($tree));
    }

    public function testFromRowsTrataComoRaizUnNodoCuyoPadreNoEsta(): void
    {
        $tree = TaxonomyTree::fromRows([self::row(2, 99, 0, 20)]);

        $this->assertSame([[20 => []]], self::shape($tree));
    }

    public function testPruneSubeLosHijosDelNodoEliminadoASuLugar(): void
    {
        $tree = TaxonomyTree::fromRows([
            self::row(1, null, 0, 10),
            self::row(2, null, 1, 20),
            self::row(3, 2, 0, 30),
            self::row(4, 2, 1, 40),
            self::row(5, null, 2, 50),
        ]);

        $pruned = TaxonomyTree::prune($tree, static fn (TreeNode $n) => $n->subjectId !== 20);

        $this->assertSame([[10 => []], [30 => []], [40 => []], [50 => []]], self::shape($pruned));
    }

    public function testParseAceptaElArbolYUsaLaClaseDeLaTaxonomiaPorDefecto(): void
    {
        $tree = TaxonomyTree::parse([
            ['subjectId' => 1, 'children' => [['subjectId' => 2], ['subjectId' => 3, 'children' => []]]],
            ['subjectId' => 4],
        ], 'MenuItem');

        $this->assertSame([[1 => [[2 => []], [3 => []]]], [4 => []]], self::shape($tree));
        $this->assertSame('MenuItem', $tree[0]->children[1]->subjectClass);
    }

    public function testWalkRecorreEnPreordenConPadreYPosicion(): void
    {
        $tree = TaxonomyTree::parse([['subjectId' => 1, 'children' => [['subjectId' => 2]]], ['subjectId' => 3]], 'MenuItem');

        $visited = [];
        foreach (TaxonomyTree::walk($tree) as ['node' => $node, 'parent' => $parent, 'position' => $position]) {
            $visited[] = [$node->subjectId, $parent?->subjectId, $position];
        }

        $this->assertSame([[1, null, 0], [2, 1, 0], [3, null, 1]], $visited);
    }

    public static function invalidos(): iterable
    {
        yield 'no es lista' => [['a' => ['subjectId' => 1]], 'MenuItem', null, 'lista de nodos'];
        yield 'sin id' => [[['children' => []]], 'MenuItem', null, 'subjectId inválido'];
        yield 'id no entero' => [[['subjectId' => '3']], 'MenuItem', null, 'subjectId inválido'];
        yield 'otra clase' => [[['subjectClass' => 'Bus', 'subjectId' => 1]], 'MenuItem', null, 'solo admite MenuItem'];
        yield 'sin clase en taxonomía libre' => [[['subjectId' => 1]], null, null, 'Falta subjectClass'];
        yield 'duplicado en otro nivel' => [[['subjectId' => 1, 'children' => [['subjectId' => 1]]]], 'MenuItem', null, 'más de una vez'];
        yield 'demasiado profundo' => [[['subjectId' => 1, 'children' => [['subjectId' => 2]]]], 'MenuItem', 0, 'Profundidad máxima 0'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidos')]
    public function testParseRechazaArbolesInvalidos(mixed $payload, ?string $class, ?int $maxDepth, string $message): void
    {
        $this->expectException(InvalidTaxonomyTree::class);
        $this->expectExceptionMessage($message);

        TaxonomyTree::parse($payload, $class, $maxDepth);
    }

    public function testParseEnTaxonomiaLibreAdmiteClasesDistintas(): void
    {
        $tree = TaxonomyTree::parse([['subjectClass' => 'Bus', 'subjectId' => 1], ['subjectClass' => 'Piloto', 'subjectId' => 1]]);

        $this->assertSame(['Bus#1', 'Piloto#1'], array_map(static fn (TreeNode $n) => $n->key(), $tree));
    }
}
