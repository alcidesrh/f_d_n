<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use Doctrine\ORM\Mapping as ORM;

/**
 * Forma de pago de una venta (efectivo, tarjeta, …). Catálogo migrado de
 * `tipo_pago` del legado (se conserva el id). El cobro con tarjeta en
 * taquilla se hace en un POS externo: el sistema solo registra la forma.
 */
#[ORM\Entity]
#[ApiResourceNoPagination]
class TipoPago extends Base
{
    #[ORM\Column(length: 50, unique: true)]
    private ?string $nombre = null;

    #[ORM\Column(options: ["default" => true])]
    private bool $activo = true;

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
