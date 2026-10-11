<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Minutos desde el origen hasta una parada para una clase de bus, cuando difieren de `Parada.minutos` (ADR-030). */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: "uq_parada_tiempo_clase", columns: ["parada_id", "bus_clase_id"])]
class ParadaTiempo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: "tiempos")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Parada $parada;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private BusClase $busClase;

    #[ORM\Column]
    private int $minutos;

    public function __construct(Parada $parada, BusClase $busClase, int $minutos)
    {
        $this->parada = $parada;
        $this->busClase = $busClase;
        $this->minutos = $minutos;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParada(): Parada
    {
        return $this->parada;
    }

    public function getBusClase(): BusClase
    {
        return $this->busClase;
    }

    public function getMinutos(): int
    {
        return $this->minutos;
    }

    public function setMinutos(int $minutos): static
    {
        $this->minutos = $minutos;

        return $this;
    }
}
