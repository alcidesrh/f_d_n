<?php

namespace App\Entity;

use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\InheritanceType("SINGLE_TABLE")]
#[ORM\DiscriminatorColumn(name: "tipo", type: "string")]
#[
    ORM\DiscriminatorMap([
        "enclave" => "Enclave",
        "estacion" => "Estacion",
    ]),
]
#[ApiResourcePaginationPage]
class Enclave extends Base
{
    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $direccion = null;

    /** Departamento (Guatemala) o distrito/estado fuera del país: agrupa los enclaves en la página web. */
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $departamento = null;

    #[ORM\Column(type: "decimal", precision: 10, scale: 7, nullable: true)]
    private ?string $latitud = null;

    #[ORM\Column(type: "decimal", precision: 10, scale: 7, nullable: true)]
    private ?string $longitud = null;

    public function __construct()
    {
        parent::__construct();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDireccion(): ?string
    {
        return $this->direccion;
    }

    public function setDireccion(?string $direccion): static
    {
        $this->direccion = $direccion;

        return $this;
    }

    public function getDepartamento(): ?string
    {
        return $this->departamento;
    }

    public function setDepartamento(?string $departamento): static
    {
        $this->departamento = $departamento;

        return $this;
    }

    public function getLatitud(): ?string
    {
        return $this->latitud;
    }

    public function setLatitud(?string $latitud): static
    {
        $this->latitud = $latitud;

        return $this;
    }

    public function getLongitud(): ?string
    {
        return $this->longitud;
    }

    public function setLongitud(?string $longitud): static
    {
        $this->longitud = $longitud;

        return $this;
    }
}
