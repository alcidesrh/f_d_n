<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\TimeLegacyStatusBase;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiResourcePaginationPage()]
class Salida extends TimeLegacyStatusBase {

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $horaPartida = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Trayecto $ruta = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Bus $bus = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Empresa $empresa = null;

    #[ORM\ManyToOne]
    private ?Tarifa $tarifa = null;

    #[ORM\Column]
    private ?bool $activa = true;

    #[ORM\OneToMany(targetEntity: Boleto::class, mappedBy: 'salida')]
    private Collection $boletos;

    public function __construct() {
        $this->boletos = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getHoraPartida(): ?\DateTimeInterface {
        return $this->horaPartida;
    }

    public function setHoraPartida(\DateTimeInterface $horaPartida): static {
        $this->horaPartida = $horaPartida;

        return $this;
    }

    public function getRuta(): ?Trayecto {
        return $this->ruta;
    }

    public function setRuta(?Trayecto $ruta): static {
        $this->ruta = $ruta;

        return $this;
    }

    public function getBus(): ?Bus {
        return $this->bus;
    }

    public function setBus(?Bus $bus): static {
        $this->bus = $bus;

        return $this;
    }

    public function getEmpresa(): ?Empresa {
        return $this->empresa;
    }

    public function setEmpresa(?Empresa $empresa): static {
        $this->empresa = $empresa;

        return $this;
    }

    public function getTarifa(): ?Tarifa {
        return $this->tarifa;
    }

    public function setTarifa(?Tarifa $tarifa): static {
        $this->tarifa = $tarifa;

        return $this;
    }

    public function getActiva(): ?bool {
        return $this->activa;
    }

    public function setActiva(bool $activa): static {
        $this->activa = $activa;

        return $this;
    }

    public function getBoletos(): Collection {
        return $this->boletos;
    }
}
