<?php

namespace App\EntitySistemaFdn;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'ruta')]
class Ruta
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 6)]
    private string $codigo;

    #[ORM\Column(type: 'string', length: 255)]
    private string $nombre;

    #[ORM\Column(type: 'integer')]
    private int $kilometros;

    #[ORM\Column(type: 'text')]
    private string $descripcion;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $internacional = null;

    #[ORM\Column(type: 'boolean')]
    private bool $activo;

    #[ORM\ManyToOne(targetEntity: Estacion::class)]
    #[ORM\JoinColumn(name: 'estacion_origen_id', referencedColumnName: 'id')]
    private ?Estacion $estacionOrigen = null;

    #[ORM\ManyToOne(targetEntity: Estacion::class)]
    #[ORM\JoinColumn(name: 'estacion_destino_id', referencedColumnName: 'id')]
    private ?Estacion $estacionDestino = null;

    #[ORM\OneToMany(mappedBy: 'ruta', targetEntity: RutaEstacionItem::class)]
    private Collection $listaEstacionesIntermediaOrdenadas;

    public function __construct()
    {
        $this->listaEstacionesIntermediaOrdenadas = new ArrayCollection();
    }

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function getKilometros(): int
    {
        return $this->kilometros;
    }

    public function getEstacionOrigen(): ?Estacion
    {
        return $this->estacionOrigen;
    }

    public function getEstacionDestino(): ?Estacion
    {
        return $this->estacionDestino;
    }

    public function getListaEstacionesIntermediaOrdenadas(): Collection
    {
        return $this->listaEstacionesIntermediaOrdenadas;
    }

    public function getActivo(): bool
    {
        return $this->activo;
    }
}
