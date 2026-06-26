<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\TimeLegacyStatusBase;
use App\Repository\VentaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VentaRepository::class)]
#[ApiResourcePaginationPage()]
class Venta extends TimeLegacyStatusBase {
    #[ORM\ManyToOne(inversedBy: 'ventas')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Usuario $usuario = null;
    /**
     * @var Collection<int, Boleto>
     */
    #[ORM\OneToMany(targetEntity: Boleto::class, mappedBy: 'venta')]
    private Collection $boletos;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?Factura $factura = null;

    #[ORM\ManyToOne(inversedBy: 'ventas')]
    private ?Enclave $enclave = null;

    #[ORM\ManyToOne(inversedBy: 'ventas')]
    private ?Empresa $empresa = null;

    public function __construct() {
        $this->boletos = new ArrayCollection();
    }

    public function getUsuario(): ?Usuario {
        return $this->usuario;
    }

    public function setUsuario(?Usuario $usuario): static {
        $this->usuario = $usuario;

        return $this;
    }

    /**
     * @return Collection<int, Boleto>
     */
    public function getBoletos(): Collection {
        return $this->boletos;
    }

    public function addBoleto(Boleto $boleto): static {
        if (!$this->boletos->contains($boleto)) {
            $this->boletos->add($boleto);
            $boleto->setVenta($this);
        }

        return $this;
    }

    public function removeBoleto(Boleto $boleto): static {
        if ($this->boletos->removeElement($boleto)) {
            // set the owning side to null (unless already changed)
            if ($boleto->getVenta() === $this) {
                $boleto->setVenta(null);
            }
        }

        return $this;
    }

    public function getFactura(): ?Factura {
        return $this->factura;
    }

    public function setFactura(?Factura $factura): static {
        $this->factura = $factura;

        return $this;
    }

    public function getEnclave(): ?Enclave {
        return $this->enclave;
    }

    public function setEnclave(?Enclave $enclave): static {
        $this->enclave = $enclave;

        return $this;
    }

    public function getEmpresa(): ?Empresa {
        return $this->empresa;
    }

    public function setEmpresa(?Empresa $empresa): static {
        $this->empresa = $empresa;

        return $this;
    }
}
