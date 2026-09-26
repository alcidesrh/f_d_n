<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\TipoBusSenal;
use Doctrine\ORM\Mapping as ORM;

/**
 * Elemento no vendible del croquis de un bus (chofer, puerta) en una celda
 * `(planta, fila, columna)`, igual que `Asiento`. Coordenadas desde 1. Se
 * gestiona entero con el croquis (`App\Croquis\CroquisBus`), no por el CRUD.
 */
#[ORM\Entity]
#[ORM\Table(name: "bus_senal")]
class BusSenal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: "senales")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Bus $bus;

    #[ORM\Column(length: 20, enumType: TipoBusSenal::class)]
    private TipoBusSenal $tipo;

    #[ORM\Column(type: "smallint", options: ["default" => 1])]
    private int $planta;

    #[ORM\Column]
    private int $fila;

    #[ORM\Column]
    private int $columna;

    public function __construct(
        Bus $bus,
        TipoBusSenal $tipo,
        int $planta,
        int $fila,
        int $columna,
    ) {
        $this->bus = $bus;
        $this->tipo = $tipo;
        $this->planta = $planta;
        $this->fila = $fila;
        $this->columna = $columna;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBus(): Bus
    {
        return $this->bus;
    }

    public function getTipo(): TipoBusSenal
    {
        return $this->tipo;
    }

    public function getPlanta(): int
    {
        return $this->planta;
    }

    public function getFila(): int
    {
        return $this->fila;
    }

    public function getColumna(): int
    {
        return $this->columna;
    }

    public function mover(int $planta, int $fila, int $columna): static
    {
        $this->planta = $planta;
        $this->fila = $fila;
        $this->columna = $columna;

        return $this;
    }
}
