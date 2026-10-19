<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Embeddable\Precio;
use App\Entity\Enum\TipoArticuloEncomienda;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;

/**
 * Artículo de una encomienda: `cantidad` bultos iguales (un paquete por peso
 * o un tipo especial), con el precio con que se cotizó (ADR-035).
 */
#[ORM\Entity]
class EncomiendaArticulo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: "articulos")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Encomienda $encomienda = null;

    #[ORM\Column(length: 20, enumType: TipoArticuloEncomienda::class)]
    private TipoArticuloEncomienda $tipo = TipoArticuloEncomienda::PAQUETE;

    #[ORM\ManyToOne]
    private ?EncomiendaEspecial $especial = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $descripcion = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $cantidad = 1;

    /** Libras por bulto (paquetes). */
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $peso = null;

    /** Medidas por bulto en centímetros (paquetes, opcionales). */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $alto = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $ancho = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $largo = null;

    /** Precio por bulto: la tarifa por peso o del especial. */
    #[ORM\Embedded(class: Precio::class, columnPrefix: "tarifa_")]
    private Precio $tarifa;

    /** Recargo por distancia por bulto (paquetes). */
    #[ORM\Embedded(class: Precio::class, columnPrefix: "recargo_")]
    private Precio $recargo;

    /** cantidad × (tarifa + recargo), antes del descuento por bultos de la encomienda. */
    #[ORM\Embedded(class: Precio::class, columnPrefix: "importe_")]
    private Precio $importe;

    public function __construct()
    {
        $this->tarifa = new Precio();
        $this->recargo = new Precio();
        $this->importe = new Precio();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEncomienda(): ?Encomienda
    {
        return $this->encomienda;
    }

    public function setEncomienda(?Encomienda $encomienda): static
    {
        $this->encomienda = $encomienda;

        return $this;
    }

    public function getTipo(): TipoArticuloEncomienda
    {
        return $this->tipo;
    }

    public function setTipo(TipoArticuloEncomienda $tipo): static
    {
        $this->tipo = $tipo;

        return $this;
    }

    public function getEspecial(): ?EncomiendaEspecial
    {
        return $this->especial;
    }

    public function setEspecial(?EncomiendaEspecial $especial): static
    {
        $this->especial = $especial;

        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion !== null ? mb_substr($descripcion, 0, 255) : null;

        return $this;
    }

    public function getCantidad(): int
    {
        return $this->cantidad;
    }

    public function setCantidad(int $cantidad): static
    {
        $this->cantidad = $cantidad;

        return $this;
    }

    public function getPeso(): ?float
    {
        return $this->peso;
    }

    public function setPeso(?float $peso): static
    {
        $this->peso = $peso;

        return $this;
    }

    public function getAlto(): ?int
    {
        return $this->alto;
    }

    public function setAlto(?int $alto): static
    {
        $this->alto = $alto;

        return $this;
    }

    public function getAncho(): ?int
    {
        return $this->ancho;
    }

    public function setAncho(?int $ancho): static
    {
        $this->ancho = $ancho;

        return $this;
    }

    public function getLargo(): ?int
    {
        return $this->largo;
    }

    public function setLargo(?int $largo): static
    {
        $this->largo = $largo;

        return $this;
    }

    public function getTarifa(): Money
    {
        return $this->tarifa->toMoney();
    }

    public function setTarifa(Money $tarifa): static
    {
        $this->tarifa = Precio::fromMoney($tarifa);

        return $this;
    }

    public function getRecargo(): Money
    {
        return $this->recargo->toMoney();
    }

    public function setRecargo(Money $recargo): static
    {
        $this->recargo = Precio::fromMoney($recargo);

        return $this;
    }

    public function getImporte(): Money
    {
        return $this->importe->toMoney();
    }

    public function setImporte(Money $importe): static
    {
        $this->importe = Precio::fromMoney($importe);

        return $this;
    }

    /** Lo que se imprime: «Sobre ×2 · reportes» / «Paquete 12 lb, 30×20×10 cm · ropa». */
    public function getResumen(): string
    {
        $base = $this->tipo === TipoArticuloEncomienda::ESPECIAL
            ? (string) $this->especial?->getNombre()
            : trim(sprintf(
                "Paquete%s%s",
                $this->peso !== null ? sprintf(" %s lb", rtrim(rtrim(number_format($this->peso, 2, ".", ""), "0"), ".")) : "",
                $this->alto && $this->ancho && $this->largo ? sprintf(", %d×%d×%d cm", $this->alto, $this->ancho, $this->largo) : "",
            ));

        return implode(" · ", array_filter([$this->cantidad > 1 ? "{$base} ×{$this->cantidad}" : $base, $this->descripcion]));
    }
}
