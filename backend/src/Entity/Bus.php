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
#[ApiResourcePaginationPage()]
class Bus extends Base {

    #[ORM\Column(length: 50)]
    private ?string $matricula = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $gama = null;


    #[ORM\ManyToOne(inversedBy: 'buses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Empresa $empresa = null;

    #[ORM\OneToMany(targetEntity: Asiento::class, mappedBy: 'bus', cascade: ['persist', 'remove'])]
    private Collection $asientos;

    #[ORM\ManyToOne]
    private ?Piloto $piloto = null;

    #[ORM\ManyToOne(targetEntity: Piloto::class)]
    #[ORM\JoinColumn(name: 'piloto_aux_id', referencedColumnName: 'id')]
    private ?Piloto $pilotoAux = null;

    #[ORM\ManyToOne(targetEntity: BusMarca::class)]
    #[ORM\JoinColumn(name: 'marca_id', referencedColumnName: 'id')]
    private ?BusMarca $marca = null;

    #[ORM\Column(type: 'string', length: 15)]
    private string $codigo;

    #[ORM\Column(name: 'anoFabricacion', type: 'integer')]
    private int $anoFabricacion;

    #[ORM\Column(name: 'numeroSeguro', type: 'string', length: 30, unique: true, nullable: true)]
    private ?string $numeroSeguro = null;

    #[ORM\Column(name: 'fechaVencimientoTarjetaOperaciones', type: 'date', nullable: true)]
    private ?\DateTime $fechaVencimientoTarjetaOperaciones = null;

    #[ORM\Column(name: 'numeroTarjetaRodaje', type: 'string', length: 50, nullable: true)]
    private ?string $numeroTarjetaRodaje = null;

    #[ORM\Column(name: 'numeroTarjetaOperaciones', type: 'string', length: 50, nullable: true)]
    private ?string $numeroTarjetaOperaciones = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descripcion = null;

    public function __construct() {
        $this->asientos = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getMatricula(): ?string {
        return $this->matricula;
    }

    public function setMatricula(string $matricula): static {
        $this->matricula = $matricula;

        return $this;
    }

    public function getGama(): ?string {
        return $this->gama;
    }

    public function setGama(?string $gama): static {
        $this->gama = $gama;

        return $this;
    }

    public function getEmpresa(): ?Empresa {
        return $this->empresa;
    }

    public function setEmpresa(?Empresa $empresa): static {
        $this->empresa = $empresa;

        return $this;
    }

    public function getAsientos(): Collection {
        return $this->asientos;
    }

    public function addAsiento(Asiento $asiento): static {
        if (!$this->asientos->contains($asiento)) {
            $this->asientos->add($asiento);
            $asiento->setBus($this);
        }

        return $this;
    }

    public function removeAsiento(Asiento $asiento): static {
        if ($this->asientos->contains($asiento)) {
            $this->asientos->removeElement($asiento);
            if ($asiento->getBus() === $this) {
                $asiento->setBus(null);
            }
        }

        return $this;
    }


    public function getPiloto(): ?Piloto {
        return $this->piloto;
    }

    public function setPiloto(?Piloto $piloto): static {
        $this->piloto = $piloto;

        return $this;
    }

    public function setAsientos(Collection $asientos): static {
        $this->asientos = $asientos;

        return $this;
    }

    public function getPilotoAux(): ?Piloto {
        return $this->pilotoAux;
    }

    public function setPilotoAux(?Piloto $pilotoAux): static {
        $this->pilotoAux = $pilotoAux;

        return $this;
    }

    public function getMarca(): ?BusMarca {
        return $this->marca;
    }

    public function setMarca(?BusMarca $marca): static {
        $this->marca = $marca;

        return $this;
    }

    public function getCodigo(): string {
        return $this->codigo;
    }

    public function setCodigo(string $codigo): static {
        $this->codigo = $codigo;

        return $this;
    }

    public function getAnoFabricacion(): int {
        return $this->anoFabricacion;
    }

    public function setAnoFabricacion(int $anoFabricacion): static {
        $this->anoFabricacion = $anoFabricacion;

        return $this;
    }

    public function getNumeroSeguro(): ?string {
        return $this->numeroSeguro;
    }

    public function setNumeroSeguro(?string $numeroSeguro): static {
        $this->numeroSeguro = $numeroSeguro;

        return $this;
    }

    public function getFechaVencimientoTarjetaOperaciones(): ?\DateTime {
        return $this->fechaVencimientoTarjetaOperaciones;
    }

    public function setFechaVencimientoTarjetaOperaciones(?\DateTime $fechaVencimientoTarjetaOperaciones): static {
        $this->fechaVencimientoTarjetaOperaciones = $fechaVencimientoTarjetaOperaciones;

        return $this;
    }

    public function getNumeroTarjetaRodaje(): ?string {
        return $this->numeroTarjetaRodaje;
    }

    public function setNumeroTarjetaRodaje(?string $numeroTarjetaRodaje): static {
        $this->numeroTarjetaRodaje = $numeroTarjetaRodaje;

        return $this;
    }

    public function getNumeroTarjetaOperaciones(): ?string {
        return $this->numeroTarjetaOperaciones;
    }

    public function setNumeroTarjetaOperaciones(?string $numeroTarjetaOperaciones): static {
        $this->numeroTarjetaOperaciones = $numeroTarjetaOperaciones;

        return $this;
    }

    public function getDescripcion(): ?string {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static {
        $this->descripcion = $descripcion;

        return $this;
    }
}
