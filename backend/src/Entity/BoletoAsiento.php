<?php

namespace App\Entity;

use App\Entity\Embeddable\Precio;
use App\Repository\BoletoAsientoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BoletoAsientoRepository::class)]
class BoletoAsiento
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Asiento $asiento = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Trayecto $trayecto = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Cliente $cliente = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Status $status = null;

    #[ORM\ManyToOne(inversedBy: "asientos")]
    #[ORM\JoinColumn(nullable: false)]
    private ?BoletoVenta $boletoVenta = null;

    #[ORM\ManyToOne(inversedBy: "boletoAsientos")]
    #[ORM\JoinColumn(nullable: false)]
    private ?Itinerario $itinerario = null;

    #[ORM\Embedded(class: Precio::class)]
    private ?Precio $precio = null;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $legacyId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAsiento(): ?Asiento
    {
        return $this->asiento;
    }

    public function setAsiento(?Asiento $asiento): static
    {
        $this->asiento = $asiento;

        return $this;
    }

    public function getTrayecto(): ?Trayecto
    {
        return $this->trayecto;
    }

    public function setTrayecto(?Trayecto $trayecto): static
    {
        $this->trayecto = $trayecto;

        return $this;
    }

    public function getCliente(): ?Cliente
    {
        return $this->cliente;
    }

    public function setCliente(?Cliente $cliente): static
    {
        $this->cliente = $cliente;

        return $this;
    }

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(?Status $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getBoletoVenta(): ?BoletoVenta
    {
        return $this->boletoVenta;
    }

    public function setBoletoVenta(?BoletoVenta $boletoVenta): static
    {
        $this->boletoVenta = $boletoVenta;

        return $this;
    }

    public function getItinerario(): ?Itinerario
    {
        return $this->itinerario;
    }

    public function setItinerario(?Itinerario $itinerario): static
    {
        $this->itinerario = $itinerario;

        return $this;
    }

    public function getPrecio(): ?Money
    {
        return $this->precio;
    }

    public function setPrecio(Money $money): self
    {
        $this->precio = Precio::fromMoney($money);
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
}
