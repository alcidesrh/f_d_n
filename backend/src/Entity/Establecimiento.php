<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use Doctrine\ORM\Mapping as ORM;

/**
 * Establecimiento de una empresa ante la SAT (FEL): el `codigo` con el que
 * se emiten sus facturas. Cada estación que vende tiene el suyo; el de
 * `estacion` null es el por defecto de la empresa (venta en línea y
 * estaciones sin establecimiento propio). ADR-021.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: "uq_establecimiento_empresa_estacion", columns: ["empresa_id", "estacion_id"])]
#[ApiResourcePaginationPage]
class Establecimiento extends Base
{
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Empresa $empresa = null;

    /** Estación que factura con este establecimiento; null = por defecto de la empresa. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    private ?Estacion $estacion = null;

    /** Número de establecimiento registrado en la SAT. */
    #[ORM\Column(type: "smallint")]
    private int $codigo = 1;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nombre = null;

    public function getEmpresa(): ?Empresa
    {
        return $this->empresa;
    }

    public function setEmpresa(?Empresa $empresa): static
    {
        $this->empresa = $empresa;

        return $this;
    }

    public function getEstacion(): ?Estacion
    {
        return $this->estacion;
    }

    public function setEstacion(?Estacion $estacion): static
    {
        $this->estacion = $estacion;

        return $this;
    }

    public function getCodigo(): int
    {
        return $this->codigo;
    }

    public function setCodigo(int $codigo): static
    {
        $this->codigo = $codigo;

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
