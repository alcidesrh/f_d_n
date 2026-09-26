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

    /**
     * Planta del bus: 1 = baja (la única en buses de un piso; en los de dos,
     * la de los asientos clase B), 2 = alta. Coordenadas `fila`/`columna`
     * desde 1 dentro de la planta (ver `App\Croquis`).
     */
    #[ORM\Column(type: "smallint", options: ["default" => 1])]
    private int $planta = 1;

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

    public function getPlanta(): int
    {
        return $this->planta;
    }

    public function setPlanta(int $planta): static
    {
        $this->planta = $planta;

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
