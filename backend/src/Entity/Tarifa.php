<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiResourcePaginationPage]
class Tarifa extends Base {

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $precioClaseA = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $precioClaseB = null;

    #[ORM\ManyToOne]
    private ?Empresa $empresa = null;

    #[ORM\ManyToOne]
    private ?Bus $bus = null;

    #[ORM\ManyToMany(targetEntity: Trayecto::class, inversedBy: 'tarifas')]
    private Collection $trayectos;

    public function __construct() {
        $this->trayectos = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getNombre(): ?string {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static {
        $this->nombre = $nombre;

        return $this;
    }

    public function getPrecioClaseA(): ?string {
        return $this->precioClaseA;
    }

    public function setPrecioClaseA(string $precioClaseA): static {
        $this->precioClaseA = $precioClaseA;

        return $this;
    }

    public function getPrecioClaseB(): ?string {
        return $this->precioClaseB;
    }

    public function setPrecioClaseB(?string $precioClaseB): static {
        $this->precioClaseB = $precioClaseB;

        return $this;
    }

    public function getEmpresa(): ?Empresa {
        return $this->empresa;
    }

    public function setEmpresa(?Empresa $empresa): static {
        $this->empresa = $empresa;

        return $this;
    }

    public function getBus(): ?Bus {
        return $this->bus;
    }

    public function setBus(?Bus $bus): static {
        $this->bus = $bus;

        return $this;
    }

    public function getTrayectos(): Collection {
        return $this->trayectos;
    }

}
