<?php

declare(strict_types=1);

namespace App\Entity;

use App\Acceso\Recurso;
use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use App\Entity\Enum\TipoCombustible;
use App\Repository\PrecioCombustibleRepository;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Precio del combustible en el mercado nacional, por día y tipo (ADR-073).
 * Lo carga quien tiene `combustible.precios`: el MEM publica referencias
 * semanales en PDF y no hay una fuente diaria que se pueda leer sola. Una
 * salida usa el precio de su día; si no hay, el último anterior (`vigente`).
 */
#[ORM\Entity(repositoryClass: PrecioCombustibleRepository::class)]
#[ORM\UniqueConstraint(name: "uq_precio_combustible_dia_tipo", columns: ["fecha", "tipo"])]
#[ApiResourcePaginationPage]
#[Recurso(nombre: "Precios de combustible", area: "Flota", lecturaLibre: true, editar: "combustible.precios", eliminar: "combustible.precios")]
class PrecioCombustible extends Base
{
    /** Día al que aplica el precio. */
    #[Assert\NotNull(message: "La fecha es obligatoria.")]
    #[ORM\Column(type: "date")]
    private ?\DateTime $fecha = null;

    #[Assert\NotNull(message: "Indique el tipo de combustible.")]
    #[ORM\Column(type: "string", length: 20, enumType: TipoCombustible::class)]
    private ?TipoCombustible $tipo = null;

    /** En quetzales por galón. */
    #[Assert\NotNull(message: "El precio es obligatorio.")]
    #[Assert\Type("numeric", message: "El precio debe ser un número.")]
    #[Assert\Positive(message: "El precio debe ser mayor que cero.")]
    #[ORM\Column(type: "decimal", precision: 10, scale: 2)]
    private ?string $precio = null;

    /** De dónde sale el dato (MEM, una estación, el de un día concreto); libre. */
    #[Assert\Length(max: 120)]
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $fuente = null;

    public function getFecha(): ?\DateTime
    {
        return $this->fecha;
    }

    public function setFecha(?\DateTime $fecha): static
    {
        $this->fecha = $fecha;

        return $this;
    }

    public function getTipo(): ?TipoCombustible
    {
        return $this->tipo;
    }

    public function setTipo(?TipoCombustible $tipo): static
    {
        $this->tipo = $tipo;

        return $this;
    }

    /** Quetzales por galón, como lo guarda la base (`12.34`). */
    public function getPrecio(): ?string
    {
        return $this->precio;
    }

    public function setPrecio(?string $precio): static
    {
        $this->precio = $precio;

        return $this;
    }

    public function getFuente(): ?string
    {
        return $this->fuente;
    }

    public function setFuente(?string $fuente): static
    {
        $this->fuente = $fuente === null || trim($fuente) === "" ? null : trim($fuente);

        return $this;
    }

    #[Override]
    public function getLabel(): string
    {
        return trim(sprintf(
            "%s · %s · Q %s/gal",
            $this->tipo?->nombre() ?? "?",
            $this->fecha?->format("d/m/Y") ?? "",
            $this->precio ?? "?",
        ));
    }
}
