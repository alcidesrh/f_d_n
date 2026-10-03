<?php

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use Doctrine\ORM\Mapping as ORM;

/**
 * Clase de servicio del bus (Económica, Clase Oro, Platino…). La tarifa
 * depende de ella (`BoletoTarifa.busClase`). En el legado, `bus_clase`, y el
 * bus la tenía a través de su tipo (`bus_tipo.clase_id`).
 */
#[ORM\Entity]
#[ApiResourceNoPagination]
class BusClase extends Base
{
    #[ORM\Column(length: 50)]
    private string $nombre;

    #[ORM\Column(options: ["default" => true])]
    private bool $activo = true;

    public function getNombre(): string
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
