<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiResourcePaginationPage]
class Trayecto extends Base {

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Enclave $origen = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Enclave $destino = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $distanciaKm = null;

    #[ORM\Column(nullable: true)]
    private ?int $duracionEstimadaMinutos = null;

    #[ORM\Column]
    private ?bool $activo = true;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $legacyId = null;

    #[ORM\ManyToMany(targetEntity: self::class, inversedBy: 'trayectosPadres')]
    #[ORM\JoinTable(name: 'trayecto_trayecto')]
    private Collection $trayectosHijos;

    #[ORM\ManyToMany(targetEntity: self::class, mappedBy: 'trayectosHijos')]
    private Collection $trayectosPadres;

    /**
     * @var Collection<int, Recorrido>
     */
    #[ORM\OneToMany(targetEntity: Recorrido::class, mappedBy: 'trayecto')]
    private Collection $recorridos;


    public function __construct() {
        $this->trayectosHijos = new ArrayCollection();
        $this->trayectosPadres = new ArrayCollection();
        $this->recorridos = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getOrigen(): ?Enclave {
        return $this->origen;
    }

    public function setOrigen(?Enclave $origen): static {
        $this->origen = $origen;

        return $this;
    }

    public function getDestino(): ?Enclave {
        return $this->destino;
    }

    public function setDestino(?Enclave $destino): static {
        $this->destino = $destino;

        return $this;
    }

    public function getDistanciaKm(): ?string {
        return $this->distanciaKm;
    }

    public function setDistanciaKm(?string $distanciaKm): static {
        $this->distanciaKm = $distanciaKm;

        return $this;
    }

    public function getDuracionEstimadaMinutos(): ?int {
        return $this->duracionEstimadaMinutos;
    }

    public function setDuracionEstimadaMinutos(?int $duracionEstimadaMinutos): static {
        $this->duracionEstimadaMinutos = $duracionEstimadaMinutos;

        return $this;
    }

    public function getActivo(): ?bool {
        return $this->activo;
    }

    public function setActivo(bool $activo): static {
        $this->activo = $activo;

        return $this;
    }
    public function getTrayectosHijos(): Collection {
        return $this->trayectosHijos;
    }

    public function getTrayectosPadres(): Collection {
        return $this->trayectosPadres;
    }


    public function getLegacyId(): ?string {
        return $this->legacyId;
    }

    public function setLegacyId(?string $legacyId): static {
        $this->legacyId = $legacyId;

        return $this;
    }

    /**
     * @return Collection<int, Recorrido>
     */
    public function getRecorridos(): Collection {
        return $this->recorridos;
    }

    public function addRecorrido(Recorrido $recorrido): static {
        if (!$this->recorridos->contains($recorrido)) {
            $this->recorridos->add($recorrido);
            $recorrido->setTrayecto($this);
        }

        return $this;
    }

    public function removeRecorrido(Recorrido $recorrido): static {
        if ($this->recorridos->removeElement($recorrido)) {
            // set the owning side to null (unless already changed)
            if ($recorrido->getTrayecto() === $this) {
                $recorrido->setTrayecto(null);
            }
        }

        return $this;
    }
}
