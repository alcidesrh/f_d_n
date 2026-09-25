<?php

declare(strict_types=1);

namespace App\Taxonomy;

use App\Entity\TaxonomyNode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lee árboles de taxonomía con un número fijo de consultas: una para los
 * nodos de todas las taxonomías pedidas y una por clase de sujeto. Los nodos
 * cuyo sujeto ya no existe se podan (sus hijos suben a su lugar).
 */
final class TaxonomyTreeReader
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SubjectRegistry $subjects,
    ) {}

    /**
     * @param list<int> $taxonomyIds
     * @param (callable(string $subjectClass, list<int> $ids): array<int, object>)|null $load
     *        cargador de sujetos (p. ej. con joins propios); por defecto `SubjectRegistry::load`
     *
     * @return array<int, list<TreeNode>> bosque por id de taxonomía (todas las pedidas, aunque vacías)
     */
    public function read(array $taxonomyIds, ?callable $load = null): array
    {
        $forests = array_fill_keys($taxonomyIds, []);
        if ($taxonomyIds === []) {
            return $forests;
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(n.taxonomy) AS taxonomy', 'n.id', 'IDENTITY(n.parent) AS parent', 'n.position', 'n.subjectClass', 'n.subjectId')
            ->from(TaxonomyNode::class, 'n')
            ->where('n.taxonomy IN (:taxonomies)')
            ->setParameter('taxonomies', $taxonomyIds)
            ->getQuery()
            ->getArrayResult();

        $byTaxonomy = [];
        $idsByClass = [];
        foreach ($rows as $row) {
            $row['parent'] = $row['parent'] !== null ? (int) $row['parent'] : null;
            $byTaxonomy[(int) $row['taxonomy']][] = $row;
            $idsByClass[$row['subjectClass']][] = $row['subjectId'];
        }

        $load ??= $this->subjects->load(...);
        $loaded = [];
        foreach ($idsByClass as $class => $ids) {
            $loaded[$class] = $load($class, $ids);
        }

        foreach ($byTaxonomy as $taxonomyId => $taxonomyRows) {
            $forests[$taxonomyId] = TaxonomyTree::prune(
                TaxonomyTree::fromRows($taxonomyRows),
                static function (TreeNode $node) use ($loaded): bool {
                    $node->subject = $loaded[$node->subjectClass][$node->subjectId] ?? null;

                    return $node->subject !== null;
                },
            );
        }

        return $forests;
    }
}
