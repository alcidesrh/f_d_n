<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Nodo de una `Taxonomy`: lista de adyacencia (`parent`) + orden entre
 * hermanos (`position`) + referencia polimórfica al registro clasificado.
 *
 * La referencia `(subjectClass, subjectId)` no tiene clave foránea: si el
 * sujeto se borra, el lector (`TaxonomyTreeReader`) ignora el nodo y sube sus
 * hijos a su lugar; el siguiente guardado del árbol lo elimina.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_taxonomy_node_subject', columns: ['taxonomy_id', 'subject_class', 'subject_id'])]
#[ORM\Index(name: 'idx_taxonomy_node_subject', columns: ['subject_class', 'subject_id'])]
class TaxonomyNode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'nodes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Taxonomy $taxonomy = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?self $parent = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(length: 100)]
    private string $subjectClass;

    #[ORM\Column]
    private int $subjectId;

    public function __construct(string $subjectClass, int $subjectId)
    {
        $this->subjectClass = $subjectClass;
        $this->subjectId = $subjectId;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaxonomy(): ?Taxonomy
    {
        return $this->taxonomy;
    }

    public function setTaxonomy(?Taxonomy $taxonomy): static
    {
        $this->taxonomy = $taxonomy;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getSubjectClass(): string
    {
        return $this->subjectClass;
    }

    public function getSubjectId(): int
    {
        return $this->subjectId;
    }
}
