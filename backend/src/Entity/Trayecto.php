<?php

namespace App\Entity;

use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: "uq_trayecto_origen_destino", columns: ["origen_id", "destino_id"])]
#[ApiResourcePaginationPage]
class Trayecto extends Base
{
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Enclave $origen = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Enclave $destino = null;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2, nullable: true)]
    private ?string $distanciaKm = null;

    #[ORM\Column(nullable: true)]
    private ?int $duracionEstimadaMinutos = null;

    #[ORM\Column]
    private ?bool $activo = true;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $legacyId = null;

    /**
     * @var Collection<int, Subtrayecto>
     */
    #[ORM\OneToMany(targetEntity: Subtrayecto::class, mappedBy: 'belowTo', orphanRemoval: true)]
    private Collection $subtrayectos;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nombre = null;

    public function __construct()
    {
        $this->subtrayectos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrigen(): ?Enclave
    {
        return $this->origen;
    }

    public function setOrigen(?Enclave $origen): static
    {
        $this->origen = $origen;

        return $this;
    }

    public function getDestino(): ?Enclave
    {
        return $this->destino;
    }

    public function setDestino(?Enclave $destino): static
    {
        $this->destino = $destino;

        return $this;
    }

    public function getDistanciaKm(): ?string
    {
        return $this->distanciaKm;
    }

    public function setDistanciaKm(?string $distanciaKm): static
    {
        $this->distanciaKm = $distanciaKm;

        return $this;
    }

    public function getDuracionEstimadaMinutos(): ?int
    {
        return $this->duracionEstimadaMinutos;
    }

    public function setDuracionEstimadaMinutos(
        ?int $duracionEstimadaMinutos,
    ): static {
        $this->duracionEstimadaMinutos = $duracionEstimadaMinutos;

        return $this;
    }

    public function getActivo(): ?bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;

        return $this;
    }

    public function getLegacyId(): ?string
    {
        return $this->legacyId;
    }

    public function setLegacyId(?string $legacyId): static
    {
        $this->legacyId = $legacyId;

        return $this;
    }

    /**
     * @return Collection<int, Subtrayecto>
     */
    public function getSubtrayectos(): Collection
    {
        return $this->subtrayectos;
    }

    public function addSubtrayecto(Subtrayecto $subtrayecto): static
    {
        if (!$this->subtrayectos->contains($subtrayecto)) {
            $this->subtrayectos->add($subtrayecto);
            $subtrayecto->setBelowTo($this);
        }

        return $this;
    }

    public function removeSubtrayecto(Subtrayecto $subtrayecto): static
    {
        if ($this->subtrayectos->removeElement($subtrayecto)) {
            // set the owning side to null (unless already changed)
            if ($subtrayecto->getBelowTo() === $this) {
                $subtrayecto->setBelowTo(null);
            }
        }

        return $this;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(?string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }
}
