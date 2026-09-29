<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tipo de documento de identificación de un cliente (DPI, pasaporte, …).
 * Catálogo migrado de `tipo_documento` del legado.
 */
#[ORM\Entity]
#[ApiResourceNoPagination]
class TipoDocumento extends Base
{
    #[ORM\Column(length: 3, nullable: true)]
    private ?string $sigla = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $nombre = null;

    #[ORM\Column(options: ["default" => true])]
    private bool $activo = true;

    public function getSigla(): ?string
    {
        return $this->sigla;
    }

    public function setSigla(?string $sigla): static
    {
        $this->sigla = $sigla;

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
