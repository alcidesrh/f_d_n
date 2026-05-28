<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiResource]
class Bus
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $matricula = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $gama = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $legacyId = null;

    #[ORM\ManyToOne(inversedBy: 'buses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Empresa $empresa = null;

    #[ORM\OneToMany(targetEntity: Asiento::class, mappedBy: 'bus', cascade: ['persist', 'remove'])]
    private Collection $asientos;

    public function __construct()
    {
        $this->asientos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMatricula(): ?string
    {
        return $this->matricula;
    }

    public function setMatricula(string $matricula): static
    {
        $this->matricula = $matricula;

        return $this;
    }

    public function getGama(): ?string
    {
        return $this->gama;
    }

    public function setGama(?string $gama): static
    {
        $this->gama = $gama;

        return $this;
    }

    public function getEmpresa(): ?Empresa
    {
        return $this->empresa;
    }

    public function setEmpresa(?Empresa $empresa): static
    {
        $this->empresa = $empresa;

        return $this;
    }

    public function getAsientos(): Collection
    {
        return $this->asientos;
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
}
