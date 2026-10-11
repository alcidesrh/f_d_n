<?php

namespace App\Entity;

use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use Doctrine\ORM\Mapping as ORM;

/**
 * Par comercial origen→destino (ADR-030): lo que pide el cliente, lo que
 * lleva tarifa y lo que se factura. Único por par (ADR-012). No describe el
 * camino del bus: eso es la `Ruta` con sus `Parada`. Existe el de cada par
 * de paradas en orden de cada ruta (`App\Ruta\Trayectos`).
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: "uq_trayecto_origen_destino", columns: ["origen_id", "destino_id"])]
#[ApiResourcePaginationPage]
class Trayecto extends Base
{
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Enclave $origen = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Enclave $destino = null;

    #[ORM\Column]
    private ?bool $activo = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nombre = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getActivo(): ?bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;

        return $this;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(?string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }
}
