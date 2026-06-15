<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use App\Attribute\ApiResourcePaginationPage;
use App\Enum\TipoAsiento;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiResourcePaginationPage()]
class Asiento {
    public const ASIENTO_CLASE_A = 'A';
    public const ASIENTO_CLASE_B = 'B';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $numero = null;

    #[ORM\Column(type: 'string', length: 1, enumType: TipoAsiento::class)]
    private ?TipoAsiento $clase = null;

    #[ORM\Column(nullable: true)]
    private ?int $fila = null;

    #[ORM\Column(nullable: true)]
    private ?int $columna = null;

    #[ORM\ManyToOne(inversedBy: 'asientos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Bus $bus = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getNumero(): ?int {
        return $this->numero;
    }

    public function setNumero(int $numero): static {
        $this->numero = $numero;

        return $this;
    }

    public function getClase(): ?TipoAsiento {
        return $this->clase;
    }

    public function setClase(TipoAsiento $clase): static {
        $this->clase = $clase;

        return $this;
    }

    public function getFila(): ?int {
        return $this->fila;
    }

    public function setFila(?int $fila): static {
        $this->fila = $fila;

        return $this;
    }

    public function getColumna(): ?int {
        return $this->columna;
    }

    public function setColumna(?int $columna): static {
        $this->columna = $columna;

        return $this;
    }

    public function getBus(): ?Bus {
        return $this->bus;
    }

    public function setBus(?Bus $bus): static {
        $this->bus = $bus;

        return $this;
    }

}
