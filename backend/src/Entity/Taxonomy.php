<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Base\Base;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Esquema taxonómico agnóstico (ver ADR-018): un árbol ordenado cuyos nodos
 * (`TaxonomyNode`) apuntan a registros de cualquier entidad del dominio por
 * referencia polimórfica `(subjectClass, subjectId)`.
 *
 * Una misma información admite varias taxonomías (p. ej. un `MenuItem` en
 * varios menús, cada uno con su propia jerarquía y orden) sin crear una
 * entidad `XxxTaxonomia` por cada entidad clasificada.
 *
 * Invariantes que aplica `App\Taxonomy\TaxonomyTreeWriter`:
 * - si `subjectClass` está fijado, todos los nodos clasifican esa entidad;
 * - un sujeto aparece como mucho una vez por taxonomía (índice único);
 * - la profundidad no supera `maxDepth` (0 = solo raíces) cuando está fijado.
 */
#[ORM\Entity]
class Taxonomy extends Base
{
    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    /** Código estable para localizar la taxonomía desde código (opcional). */
    #[ORM\Column(length: 100, unique: true, nullable: true)]
    private ?string $codigo = null;

    /** Nombre corto de la entidad clasificada (`MenuItem`); null = heterogénea. */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $subjectClass = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxDepth = null;

    /**
     * @var Collection<int, TaxonomyNode>
     */
    #[ORM\OneToMany(targetEntity: TaxonomyNode::class, mappedBy: 'taxonomy', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $nodes;

    public function __construct(string $nombre = '', ?string $subjectClass = null)
    {
        $this->nombre = $nombre;
        $this->subjectClass = $subjectClass;
        $this->nodes = new ArrayCollection();
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getCodigo(): ?string
    {
        return $this->codigo;
    }

    public function setCodigo(?string $codigo): static
    {
        $this->codigo = $codigo;

        return $this;
    }

    public function getSubjectClass(): ?string
    {
        return $this->subjectClass;
    }

    public function setSubjectClass(?string $subjectClass): static
    {
        $this->subjectClass = $subjectClass;

        return $this;
    }

    public function getMaxDepth(): ?int
    {
        return $this->maxDepth;
    }

    public function setMaxDepth(?int $maxDepth): static
    {
        $this->maxDepth = $maxDepth;

        return $this;
    }

    /**
     * @return Collection<int, TaxonomyNode>
     */
    public function getNodes(): Collection
    {
        return $this->nodes;
    }

    public function addNode(TaxonomyNode $node): static
    {
        if (!$this->nodes->contains($node)) {
            $this->nodes->add($node);
            $node->setTaxonomy($this);
        }

        return $this;
    }

    public function removeNode(TaxonomyNode $node): static
    {
        $this->nodes->removeElement($node);

        return $this;
    }
}
