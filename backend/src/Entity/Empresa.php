<?php

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiResourceNoPagination()]
class Empresa extends Base {

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $nit = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $direccion = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telefono = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\OneToMany(targetEntity: Bus::class, mappedBy: 'empresa')]
    private Collection $buses;

    /**
     * @var Collection<int, Venta>
     */
    #[ORM\OneToMany(targetEntity: Venta::class, mappedBy: 'empresa')]
    private Collection $ventas;

    public function __construct() {
        $this->buses = new ArrayCollection();
        $this->ventas = new ArrayCollection();
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

    public function getNit(): ?string {
        return $this->nit;
    }

    public function setNit(?string $nit): static {
        $this->nit = $nit;

        return $this;
    }

    public function getDireccion(): ?string {
        return $this->direccion;
    }

    public function setDireccion(?string $direccion): static {
        $this->direccion = $direccion;

        return $this;
    }

    public function getTelefono(): ?string {
        return $this->telefono;
    }

    public function setTelefono(?string $telefono): static {
        $this->telefono = $telefono;

        return $this;
    }

    public function getEmail(): ?string {
        return $this->email;
    }

    public function setEmail(?string $email): static {
        $this->email = $email;

        return $this;
    }

    public function getBuses(): Collection {
        return $this->buses;
    }

    /**
     * @return Collection<int, Venta>
     */
    public function getVentas(): Collection {
        return $this->ventas;
    }

    public function addVenta(Venta $venta): static {
        if (!$this->ventas->contains($venta)) {
            $this->ventas->add($venta);
            $venta->setEmpresa($this);
        }

        return $this;
    }

    public function removeVenta(Venta $venta): static {
        if ($this->ventas->removeElement($venta)) {
            // set the owning side to null (unless already changed)
            if ($venta->getEmpresa() === $this) {
                $venta->setEmpresa(null);
            }
        }

        return $this;
    }
}
