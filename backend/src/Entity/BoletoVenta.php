<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\BoletoVentaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BoletoVentaRepository::class)]
#[ApiResource]
class BoletoVenta
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Usuario $usuario = null;

    /**
     * @var Collection<int, BoletoAsiento>
     */
    #[
        ORM\OneToMany(
            targetEntity: BoletoAsiento::class,
            mappedBy: "boletoVenta",
            orphanRemoval: true,
        ),
    ]
    private Collection $asientos;

    #[ORM\OneToOne(cascade: ["persist", "remove"])]
    private ?Factura $factura = null;

    public function __construct()
    {
        $this->asientos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsuario(): ?Usuario
    {
        return $this->usuario;
    }

    public function setUsuario(?Usuario $usuario): static
    {
        $this->usuario = $usuario;

        return $this;
    }

    /**
     * @return Collection<int, BoletoAsiento>
     */
    public function getAsientos(): Collection
    {
        return $this->asientos;
    }

    public function addAsiento(BoletoAsiento $asiento): static
    {
        if (!$this->asientos->contains($asiento)) {
            $this->asientos->add($asiento);
            $asiento->setBoletoVenta($this);
        }

        return $this;
    }

    public function removeAsiento(BoletoAsiento $asiento): static
    {
        if ($this->asientos->removeElement($asiento)) {
            // set the owning side to null (unless already changed)
            if ($asiento->getBoletoVenta() === $this) {
                $asiento->setBoletoVenta(null);
            }
        }

        return $this;
    }

    public function getFactura(): ?Factura
    {
        return $this->factura;
    }

    public function setFactura(?Factura $factura): static
    {
        $this->factura = $factura;

        return $this;
    }
}
