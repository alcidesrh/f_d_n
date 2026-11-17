<?php

declare(strict_types=1);

namespace App\Entity;

use App\Acceso\Recurso;
use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Quién viaja, a efectos de la tarifa (ADR-066): niño, tercera edad,
 * estudiante… con el porcentaje que se le descuenta. Se elige por asiento al
 * vender en taquilla o en agencia; sin tipo, paga la tarifa completa.
 */
#[ORM\Entity]
#[ApiResourceNoPagination]
#[Recurso(nombre: "Tipos de pasajero", area: "Venta", lecturaLibre: true)]
class TipoPasajero extends Base
{
    #[Assert\NotBlank]
    #[ORM\Column(length: 60, unique: true)]
    private string $nombre = "";

    /** Lo que se descuenta de la tarifa, en por ciento. */
    #[Assert\Range(min: 1, max: 100)]
    #[ORM\Column(type: "smallint")]
    private int $porciento = 0;

    /** Lo que la taquilla debe pedir (p. ej. «Presentar carné de estudiante»). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $requisito = null;

    /** Uno inactivo no se ofrece al vender; los boletos que lo llevan lo conservan. */
    #[ORM\Column(options: ["default" => true])]
    private bool $activo = true;

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = trim($nombre);

        return $this;
    }

    public function getPorciento(): int
    {
        return $this->porciento;
    }

    public function setPorciento(int $porciento): static
    {
        $this->porciento = $porciento;

        return $this;
    }

    public function getRequisito(): ?string
    {
        return $this->requisito;
    }

    public function setRequisito(?string $requisito): static
    {
        $this->requisito = $requisito !== null && trim($requisito) !== "" ? trim($requisito) : null;

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
}
