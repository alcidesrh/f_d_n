<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use App\Entity\Embeddable\Precio;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Recargo por distancia que se suma a cada paquete enviado entre dos enclaves,
 * en cualquier sentido (`origen`/`destino` es solo cómo se cargó). Rige desde
 * `vigenteDesde`; gana la más reciente ya vigente. Sin recargo para el par: 0
 * (ADR-035).
 */
#[ORM\Entity]
#[ORM\Index(columns: ["origen_id", "destino_id"], name: "idx_tarifa_encomienda_distancia_par")]
#[ApiResourcePaginationPage]
class TarifaEncomiendaDistancia extends Base
{
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Enclave $origen = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Enclave $destino = null;

    #[ORM\Embedded(class: Precio::class)]
    private Precio $precio;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTime $vigenteDesde;

    public function __construct()
    {
        $this->precio = new Precio();
        $this->vigenteDesde = new \DateTime();
    }

    public function getNombre(): string
    {
        return sprintf("%s ↔ %s", $this->origen?->getNombre(), $this->destino?->getNombre());
    }

    public function getOrigen(): ?Enclave
    {
        return $this->origen;
    }

    public function setOrigen(?Enclave $origen): static
    {
        $this->origen = $origen;

        return $this;
    }

    public function getDestino(): ?Enclave
    {
        return $this->destino;
    }

    public function setDestino(?Enclave $destino): static
    {
        $this->destino = $destino;

        return $this;
    }

    public function getPrecio(): Money
    {
        return $this->precio->toMoney();
    }

    public function setPrecio(Money $precio): static
    {
        $this->precio = Precio::fromMoney($precio);

        return $this;
    }

    #[ApiProperty(writable: false)]
    public function getImporte(): string
    {
        return TarifaEncomiendaPeso::formatear($this->getPrecio());
    }

    #[ApiProperty]
    #[Assert\PositiveOrZero]
    public function getPrecioMonto(): float
    {
        return (int) $this->getPrecio()->getAmount() / 100;
    }

    public function setPrecioMonto(float $monto): static
    {
        return $this->setPrecio(Money::GTQ((int) round($monto * 100)));
    }

    public function getVigenteDesde(): \DateTime
    {
        return $this->vigenteDesde;
    }

    public function setVigenteDesde(\DateTime $vigenteDesde): static
    {
        $this->vigenteDesde = $vigenteDesde;

        return $this;
    }
}
