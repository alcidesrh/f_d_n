<?php

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use App\Entity\Enum\AsientoClase;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiResourceNoPagination]
class Asiento extends Base
{
    #[ORM\Column]
    private ?int $numero = null;

    #[ORM\Column(type: "string", length: 1, enumType: AsientoClase::class)]
    private AsientoClase $clase;

    #[ORM\Column(nullable: true)]
    private ?int $fila = null;

    #[ORM\Column(nullable: true)]
    private ?int $columna = null;

    #[ORM\ManyToOne(inversedBy: "asientos")]
    #[ORM\JoinColumn(nullable: false)]
    private ?Bus $bus = null;

    public function getNumero(): ?int
    {
        return $this->numero;
    }

    public function setNumero(int $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getFila(): ?int
    {
        return $this->fila;
    }

    public function setFila(?int $fila): static
    {
        $this->fila = $fila;

        return $this;
    }

    public function getColumna(): ?int
    {
        return $this->columna;
    }

    public function setColumna(?int $columna): static
    {
        $this->columna = $columna;

        return $this;
    }

    public function getBus(): ?Bus
    {
        return $this->bus;
    }

    public function setBus(?Bus $bus): static
    {
        $this->bus = $bus;

        return $this;
    }
    public function getClase(): AsientoClase
    {
        return $this->clase;
    }

    public function setClase(AsientoClase $clase): static
    {
        $this->clase = $clase;

        return $this;
    }
}
