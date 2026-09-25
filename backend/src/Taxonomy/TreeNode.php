<?php

declare(strict_types=1);

namespace App\Taxonomy;

/**
 * Nodo del árbol en memoria de una taxonomía: el sujeto referenciado, sus
 * hijos ya ordenados y, si se cargó, el registro del sujeto.
 */
final class TreeNode
{
    /**
     * @param list<TreeNode> $children
     */
    public function __construct(
        public readonly string $subjectClass,
        public readonly int $subjectId,
        public array $children = [],
        public ?object $subject = null,
    ) {}

    public function key(): string
    {
        return self::keyOf($this->subjectClass, $this->subjectId);
    }

    public static function keyOf(string $subjectClass, int $subjectId): string
    {
        return $subjectClass . '#' . $subjectId;
    }
}
