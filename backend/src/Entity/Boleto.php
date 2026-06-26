<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\TimeLegacyStatusBase;
use App\Entity\Embeddable\Precio;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;

#[ORM\Entity]
#[ApiResourcePaginationPage()]
class Boleto extends TimeLegacyStatusBase {

    #[ORM\ManyToOne]
    private ?Recorrido $recorrido = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Cliente $cliente = null;


    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $legacyId = null;

    #[ORM\ManyToOne(inversedBy: 'boletos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Venta $venta = null;

    #[ORM\Embedded(class: Precio::class)]
    private Precio $precio;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Asiento $asiento = null;

    #[ORM\ManyToOne(inversedBy: 'boletos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Servicio $servicio = null;

    public function getPrice(): Money {
        return $this->precio->toMoney();
    }

    public function setPrice(Money $money): self {
        $this->precio = Precio::fromMoney($money);

        return $this;
    }

    public function getRecorrido(): ?Recorrido {
        return $this->recorrido;
    }

    public function setRecorrido(?Recorrido $recorrido): static {
        $this->recorrido = $recorrido;

        return $this;
    }

    public function getCliente(): ?Cliente {
        return $this->cliente;
    }

    public function setCliente(?Cliente $cliente): static {
        $this->cliente = $cliente;

        return $this;
    }

    public function getLegacyId(): ?string {
        return $this->legacyId;
    }

    public function setLegacyId(?string $legacyId): static {
        $this->legacyId = $legacyId;

        return $this;
    }

    public function getVenta(): ?Venta {
        return $this->venta;
    }

    public function setVenta(?Venta $venta): static {
        $this->venta = $venta;

        return $this;
    }

    public function getAsiento(): ?Asiento {
        return $this->asiento;
    }

    public function setAsiento(?Asiento $asiento): static {
        $this->asiento = $asiento;

        return $this;
    }

    public function getServicio(): ?Servicio {
        return $this->servicio;
    }

    public function setServicio(?Servicio $servicio): static {
        $this->servicio = $servicio;

        return $this;
    }
}
