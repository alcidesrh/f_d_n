<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Attribute\ApiResourcePaginationPage;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiResourcePaginationPage()]
class Boleto {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $fechaCompra = null;

    #[ORM\ManyToOne(inversedBy: 'boletos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Salida $salida = null;

    #[ORM\ManyToOne]
    private ?Trayecto $trayecto = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Cliente $cliente = null;

    #[ORM\ManyToMany(targetEntity: Asiento::class)]
    private Collection $asientos;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Estacion $estacion = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $usuarioCreador = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $legacyId = null;

    #[ORM\ManyToOne]
    private ?Status $status = null;

    public function __construct() {
        $this->asientos = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getFechaCompra(): ?\DateTimeInterface {
        return $this->fechaCompra;
    }

    public function setFechaCompra(\DateTimeInterface $fechaCompra): static {
        $this->fechaCompra = $fechaCompra;

        return $this;
    }

    public function getSalida(): ?Salida {
        return $this->salida;
    }

    public function setSalida(?Salida $salida): static {
        $this->salida = $salida;

        return $this;
    }

    public function getTrayecto(): ?Trayecto {
        return $this->trayecto;
    }

    public function setTrayecto(?Trayecto $trayecto): static {
        $this->trayecto = $trayecto;

        return $this;
    }

    public function getCliente(): ?Cliente {
        return $this->cliente;
    }

    public function setCliente(?Cliente $cliente): static {
        $this->cliente = $cliente;

        return $this;
    }

    public function getAsientos(): Collection {
        return $this->asientos;
    }

    public function getEstacion(): ?Estacion {
        return $this->estacion;
    }

    public function setEstacion(?Estacion $estacion): static {
        $this->estacion = $estacion;

        return $this;
    }

    public function getUsuarioCreador(): ?string {
        return $this->usuarioCreador;
    }

    public function setUsuarioCreador(?string $usuarioCreador): static {
        $this->usuarioCreador = $usuarioCreador;

        return $this;
    }

    public function getLegacyId(): ?string {
        return $this->legacyId;
    }

    public function setLegacyId(?string $legacyId): static {
        $this->legacyId = $legacyId;

        return $this;
    }

    public function getStatus(): ?Status {
        return $this->status;
    }

    public function setStatus(?Status $status): static {
        $this->status = $status;

        return $this;
    }
}
