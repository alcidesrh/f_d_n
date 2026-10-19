<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use App\Entity\Embeddable\Precio;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Tipo de encomienda con precio fijo por unidad (sobre, llanta, bicicleta…),
 * el "tipo especial" del legado con su tarifa (ADR-035). Cambiar el precio no
 * toca las encomiendas ya registradas: cada artículo guarda el suyo.
 */
#[ORM\Entity]
#[ApiResourcePaginationPage(order: ["nombre" => "ASC"])]
class EncomiendaEspecial extends Base
{
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $nombre = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Embedded(class: Precio::class)]
    private Precio $precio;

    /** Se puede enviar por cobrar (lo paga quien la retira). */
    #[ORM\Column(options: ["default" => true])]
    private bool $permitePorCobrar = true;

    #[ORM\Column(options: ["default" => true])]
    private bool $activo = true;

    /** Id en `encomienda_especiales_tipo` del legado. */
    #[ApiProperty(readable: false, writable: false)]
    #[ORM\Column(length: 20, nullable: true, unique: true)]
    private ?string $legacyId = null;

    public function __construct()
    {
        $this->precio = new Precio();
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;

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

    /** El precio formateado (`Q 50.00`) para los listados. */
    #[ApiProperty(writable: false)]
    public function getImporte(): string
    {
        return TarifaEncomiendaPeso::formatear($this->getPrecio());
    }

    /** Precio en quetzales (`50.5`) para el formulario; se guarda en centavos. */
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

    public function isPermitePorCobrar(): bool
    {
        return $this->permitePorCobrar;
    }

    public function setPermitePorCobrar(bool $permitePorCobrar): static
    {
        $this->permitePorCobrar = $permitePorCobrar;

        return $this;
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;

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
