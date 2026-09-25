<?php

declare(strict_types=1);

namespace App\Taxonomy;

use App\Entity\Taxonomy;
use App\Entity\TaxonomyNode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Reemplaza el árbol completo de una taxonomía. Reutiliza los nodos de los
 * sujetos que siguen (solo cambia `parent`/`position`), crea los nuevos y
 * borra los que ya no están; así el índice único `(taxonomía, sujeto)` nunca
 * se viola a mitad del flush.
 */
final class TaxonomyTreeWriter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SubjectRegistry $subjects,
    ) {}

    /**
     * @param mixed $payload árbol del cliente (ver `TaxonomyTree::parse`)
     *
     * @throws InvalidTaxonomyTree si el árbol o alguno de sus sujetos no es válido
     */
    public function replace(Taxonomy $taxonomy, mixed $payload): void
    {
        $tree = TaxonomyTree::parse($payload, $taxonomy->getSubjectClass(), $taxonomy->getMaxDepth());
        $this->assertSubjectsExist($tree);

        $existing = [];
        foreach ($taxonomy->getNodes() as $node) {
            $existing[TreeNode::keyOf($node->getSubjectClass(), $node->getSubjectId())] = $node;
        }

        $entities = [];
        foreach (TaxonomyTree::walk($tree) as ['node' => $node, 'parent' => $parent, 'position' => $position]) {
            $key = $node->key();
            $entity = $existing[$key] ?? new TaxonomyNode($node->subjectClass, $node->subjectId);
            unset($existing[$key]);
            $taxonomy->addNode($entity);
            $entity->setParent($parent !== null ? $entities[$parent->key()] : null);
            $entity->setPosition($position);
            $entities[$key] = $entity;
        }

        foreach ($existing as $removed) {
            $taxonomy->removeNode($removed);
        }

        $this->entityManager->flush();
    }

    /**
     * @param list<TreeNode> $tree
     */
    private function assertSubjectsExist(array $tree): void
    {
        $idsByClass = [];
        foreach (TaxonomyTree::walk($tree) as ['node' => $node]) {
            $idsByClass[$node->subjectClass][] = $node->subjectId;
        }

        foreach ($idsByClass as $class => $ids) {
            $missing = array_diff($ids, array_keys($this->subjects->load($class, $ids)));
            if ($missing !== []) {
                throw new InvalidTaxonomyTree(sprintf('No existen %s con id %s.', $class, implode(', ', $missing)));
            }
        }
    }
}
