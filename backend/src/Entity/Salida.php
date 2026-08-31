<?php

namespace App\Entity;

use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\TimeLegacyStatusBase;
use App\Repository\SalidaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SalidaRepository::class)]
#[ApiResourcePaginationPage]
class Salida extends TimeLegacyStatusBase
{
    #[ORM\Column]
    private ?\DateTime $fecha = null;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $legacyId = null;

    #[ORM\ManyToOne]
    private ?Empresa $empresa = null;

    #[ORM\ManyToOne]
    private ?Bus $bus = null;

    #[ORM\ManyToOne]
    private ?Piloto $piloto = null;

    /**
     * @var Collection<int, BoletoAsiento>
     */
    #[ORM\OneToMany(targetEntity: BoletoAsiento::class, mappedBy: "salida")]
    private Collection $boletoAsientos;

    public function __construct()
    {
        parent::__construct();
        $this->boletoAsientos = new ArrayCollection();
    }

    public function getFecha(): ?\DateTime
    {
        return $this->fecha;
    }

    public function setFecha(\DateTime $fecha): static
    {
        $this->fecha = $fecha;

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

    public function getPiloto(): ?Piloto
    {
        return $this->piloto;
    }

    public function setPiloto(?Piloto $piloto): static
    {
        $this->piloto = $piloto;

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
     * @return Collection<int, BoletoAsiento>
     */
    public function getBoletoAsientos(): Collection
    {
        return $this->boletoAsientos;
    }

    public function addBoletoAsiento(BoletoAsiento $boletoAsiento): static
    {
        if (!$this->boletoAsientos->contains($boletoAsiento)) {
            $this->boletoAsientos->add($boletoAsiento);
            $boletoAsiento->setSalida($this);
        }

        return $this;
    }

    public function removeBoletoAsiento(BoletoAsiento $boletoAsiento): static
    {
        if ($this->boletoAsientos->removeElement($boletoAsiento)) {
            // set the owning side to null (unless already changed)
            if ($boletoAsiento->getSalida() === $this) {
                $boletoAsiento->setSalida(null);
            }
        }

        return $this;
    }
}
