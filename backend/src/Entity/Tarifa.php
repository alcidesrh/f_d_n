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
class Tarifa
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $nombre = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $precioClaseA = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $precioClaseB = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Empresa $empresa = null;

    #[ORM\ManyToOne]
    private ?Bus $bus = null;

    #[ORM\ManyToMany(targetEntity: Trayecto::class, inversedBy: 'tarifas')]
    private Collection $trayectos;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $legacyId = null;

    public function __construct()
    {
        $this->trayectos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPrecioClaseA(): ?string
    {
        return $this->precioClaseA;
    }

    public function setPrecioClaseA(string $precioClaseA): static
    {
        $this->precioClaseA = $precioClaseA;

        return $this;
    }

    public function getPrecioClaseB(): ?string
    {
        return $this->precioClaseB;
    }

    public function setPrecioClaseB(?string $precioClaseB): static
    {
        $this->precioClaseB = $precioClaseB;

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

    public function getBus(): ?Bus
    {
        return $this->bus;
    }

    public function setBus(?Bus $bus): static
    {
        $this->bus = $bus;

        return $this;
    }

    public function getTrayectos(): Collection
    {
        return $this->trayectos;
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
