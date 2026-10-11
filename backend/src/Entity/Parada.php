<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Paso de una ruta por un enclave (ADR-030). `orden` 0 es el origen.
 * `minutos` desde el origen: el de la clase del bus (`ParadaTiempo`) manda
 * cuando existe; nulo si no se conoce.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: "uq_parada_ruta_orden", columns: ["ruta_id", "orden"])]
#[ORM\UniqueConstraint(name: "uq_parada_ruta_enclave", columns: ["ruta_id", "enclave_id"])]
class Parada
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: "paradas")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Ruta $ruta;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Enclave $enclave;

    #[ORM\Column(type: "smallint")]
    private int $orden;

    #[ORM\Column(nullable: true)]
    private ?int $minutos = null;

    #[ORM\Column(nullable: true)]
    private ?int $km = null;

    #[ORM\Column(options: ["default" => true])]
    private bool $permiteSubir = true;

    #[ORM\Column(options: ["default" => true])]
    private bool $permiteBajar = true;

    /** @var Collection<int, ParadaTiempo> */
    #[ORM\OneToMany(targetEntity: ParadaTiempo::class, mappedBy: "parada", cascade: ["persist", "remove"], orphanRemoval: true)]
    private Collection $tiempos;

    public function __construct(Ruta $ruta, Enclave $enclave, int $orden)
    {
        $this->ruta = $ruta;
        $this->enclave = $enclave;
        $this->orden = $orden;
        $this->tiempos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRuta(): Ruta
    {
        return $this->ruta;
    }

    public function getEnclave(): Enclave
    {
        return $this->enclave;
    }

    public function getOrden(): int
    {
        return $this->orden;
    }

    public function setOrden(int $orden): static
    {
        $this->orden = $orden;

        return $this;
    }

    public function getMinutos(): ?int
    {
        return $this->minutos;
    }

    public function setMinutos(?int $minutos): static
    {
        $this->minutos = $minutos;

        return $this;
    }

    public function getKm(): ?int
    {
        return $this->km;
    }

    public function setKm(?int $km): static
    {
        $this->km = $km;

        return $this;
    }

    public function permiteSubir(): bool
    {
        return $this->permiteSubir;
    }

    public function setPermiteSubir(bool $permite): static
    {
        $this->permiteSubir = $permite;

        return $this;
    }

    public function permiteBajar(): bool
    {
        return $this->permiteBajar;
    }

    public function setPermiteBajar(bool $permite): static
    {
        $this->permiteBajar = $permite;

        return $this;
    }

    /** @return Collection<int, ParadaTiempo> */
    public function getTiempos(): Collection
    {
        return $this->tiempos;
    }
}
