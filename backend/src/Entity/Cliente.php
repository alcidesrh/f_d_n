<?php

namespace App\Entity;

use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\PersonaBase;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(columns: ["nit"], name: "idx_cliente_nit")]
#[ApiResourcePaginationPage()]
class Cliente extends PersonaBase {

    /** Documento de identificación (opcional): tipo + número. */
    #[ORM\ManyToOne]
    private ?TipoDocumento $tipoDocumento = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $numeroDocumento = null;

    #[ORM\ManyToOne]
    private ?Nacion $nacionalidad = null;

    public function getTipoDocumento(): ?TipoDocumento
    {
        return $this->tipoDocumento;
    }

    public function setTipoDocumento(?TipoDocumento $tipoDocumento): static
    {
        $this->tipoDocumento = $tipoDocumento;

        return $this;
    }

    public function getNumeroDocumento(): ?string
    {
        return $this->numeroDocumento;
    }

    public function setNumeroDocumento(?string $numeroDocumento): static
    {
        $this->numeroDocumento = $numeroDocumento;

        return $this;
    }

    public function getNacionalidad(): ?Nacion
    {
        return $this->nacionalidad;
    }

    public function setNacionalidad(?Nacion $nacionalidad): static
    {
        $this->nacionalidad = $nacionalidad;

        return $this;
    }

    /** Nombre completo como se imprime en boletos y facturas. */
    public function getNombreCompleto(): string
    {
        return trim(implode(" ", array_filter([
            $this->nombre,
            $this->apellido !== "S/N" ? $this->apellido : null,
        ])));
    }
}
