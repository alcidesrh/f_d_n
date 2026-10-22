<?php

declare(strict_types=1);

namespace App\Entity;

use App\Acceso\Recurso;
use ApiPlatform\Metadata\ApiProperty;
use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use App\Entity\Embeddable\Precio;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Precio de un paquete por su peso en libras: más de `pesoDesde` hasta
 * `pesoHasta` inclusive (sin tope si está vacío), así los rangos se encadenan
 * sin huecos (0–10, 10–20…). Rige desde `vigenteDesde`; si dos rangos cubren
 * el mismo peso, gana la más reciente ya vigente (ADR-035).
 */
#[ORM\Entity]
#[ORM\Index(columns: ["vigente_desde"], name: "idx_tarifa_encomienda_peso_vigente")]
#[ApiResourcePaginationPage(order: ["pesoDesde" => "ASC"])]
#[Recurso(nombre: "Tarifas de encomienda por peso", area: "Encomiendas", lecturaLibre: true)]
class TarifaEncomiendaPeso extends Base
{
    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\PositiveOrZero]
    private float $pesoDesde = 0;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Assert\Positive]
    private ?float $pesoHasta = null;

    #[ORM\Embedded(class: Precio::class)]
    private Precio $precio;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTime $vigenteDesde;

    public function __construct()
    {
        $this->precio = new Precio();
        $this->vigenteDesde = new \DateTime();
    }

    /** `0 – 10 lb`, para los selectores del listado genérico. */
    public function getNombre(): string
    {
        return sprintf("%s – %s lb", self::numero($this->pesoDesde), $this->pesoHasta === null ? "…" : self::numero($this->pesoHasta));
    }

    public function getPesoDesde(): float
    {
        return $this->pesoDesde;
    }

    public function setPesoDesde(float $pesoDesde): static
    {
        $this->pesoDesde = $pesoDesde;

        return $this;
    }

    public function getPesoHasta(): ?float
    {
        return $this->pesoHasta;
    }

    public function setPesoHasta(?float $pesoHasta): static
    {
        $this->pesoHasta = $pesoHasta;

        return $this;
    }

    public function cubre(float $peso): bool
    {
        return $peso > $this->pesoDesde && ($this->pesoHasta === null || $peso <= $this->pesoHasta);
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
        return self::formatear($this->getPrecio());
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

    /** `Q 30.00`. */
    public static function formatear(Money $monto): string
    {
        $moneda = $monto->getCurrency()->getCode();

        return sprintf("%s %s", $moneda === "GTQ" ? "Q" : $moneda, number_format((int) $monto->getAmount() / 100, 2));
    }

    private static function numero(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ".", ""), "0"), ".");
    }
}
