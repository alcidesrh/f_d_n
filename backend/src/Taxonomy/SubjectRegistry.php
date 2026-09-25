<?php

declare(strict_types=1);

namespace App\Taxonomy;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Traduce el `subjectClass` de un nodo (nombre corto: `MenuItem`) a la
 * entidad de `App\Entity` y carga sujetos por lotes (una consulta por clase).
 */
final class SubjectRegistry
{
    private const NAMESPACE = 'App\\Entity\\';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /** @return class-string */
    public function entityClass(string $subjectClass): string
    {
        $class = self::NAMESPACE . $subjectClass;
        if (!preg_match('/^[A-Z]\w*$/', $subjectClass)
            || !class_exists($class)
            || $this->entityManager->getMetadataFactory()->isTransient($class)) {
            throw new InvalidTaxonomyTree(sprintf('"%s" no es una entidad clasificable.', $subjectClass));
        }

        return $class;
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, object> sujetos existentes por id
     */
    public function load(string $subjectClass, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $found = $this->entityManager->getRepository($this->entityClass($subjectClass))
            ->findBy(['id' => array_values(array_unique($ids))]);

        $byId = [];
        foreach ($found as $subject) {
            $byId[$subject->getId()] = $subject;
        }

        return $byId;
    }
}
