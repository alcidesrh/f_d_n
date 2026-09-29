<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use Doctrine\ORM\Mapping as ORM;

/**
 * Moneda de pago de una venta. `sigla` es el código ISO 4217 (GTQ, USD, …),
 * el mismo que usa `Precio`. Catálogo migrado de `moneda` del legado.
 */
#[ORM\Entity]
#[ApiResourceNoPagination]
class Moneda extends Base
{
    #[ORM\Column(length: 3, unique: true)]
    private ?string $sigla = null;

    #[ORM\Column(length: 40)]
    private ?string $nombre = null;

    #[ORM\Column(options: ["default" => true])]
    private bool $activo = true;

    public function getSigla(): ?string
    {
        return $this->sigla;
    }

    public function setSigla(string $sigla): static
    {
        $this->sigla = strtoupper($sigla);

        return $this;
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
