<?php

namespace App\Entity;

use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\TimeLegacyStatusBase;
use App\Repository\ServicioRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ServicioRepository::class)]
#[ApiResourcePaginationPage]
class Servicio extends TimeLegacyStatusBase {
    #[ORM\Column]
    private ?\DateTime $fecha = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $legacyId = null;

    #[ORM\ManyToOne]
    private ?Empresa $empresa = null;

    #[ORM\ManyToOne(inversedBy: 'servicios')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Recorrido $recorrido = null;

    #[ORM\ManyToOne]
    private ?Bus $bus = null;

    #[ORM\ManyToOne]
    private ?Piloto $piloto = null;

    /**
     * @var Collection<int, Boleto>
     */
    #[ORM\OneToMany(targetEntity: Boleto::class, mappedBy: 'servicio')]
    private Collection $boletos;

    public function __construct() {
        parent::__construct();
        $this->boletos = new ArrayCollection();
    }


    public function getFecha(): ?\DateTime {
        return $this->fecha;
    }

    public function setFecha(\DateTime $fecha): static {
        $this->fecha = $fecha;

        return $this;
    }

    public function getEmpresa(): ?Empresa {
        return $this->empresa;
    }

    public function setEmpresa(?Empresa $empresa): static {
        $this->empresa = $empresa;

        return $this;
    }

    public function getRecorrido(): ?Recorrido {
        return $this->recorrido;
    }

    public function setRecorrido(?Recorrido $recorrido): static {
        $this->recorrido = $recorrido;

        return $this;
    }

    public function getBus(): ?Bus {
        return $this->bus;
    }

    public function setBus(?Bus $bus): static {
        $this->bus = $bus;

        return $this;
    }

    public function getPiloto(): ?Piloto {
        return $this->piloto;
    }

    public function setPiloto(?Piloto $piloto): static {
        $this->piloto = $piloto;

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
            $boleto->setServicio($this);
        }

        return $this;
    }

    public function removeBoleto(Boleto $boleto): static {
        if ($this->boletos->removeElement($boleto)) {
            // set the owning side to null (unless already changed)
            if ($boleto->getServicio() === $this) {
                $boleto->setServicio(null);
            }
        }

        return $this;
    }
    public function getLegacyId(): ?string {
        return $this->legacyId;
    }

    public function setLegacyId(?string $legacyId): static {
        $this->legacyId = $legacyId;
        return $this;
    }
}
