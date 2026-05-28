<?php

namespace App\EntitySistemaFdn;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'bus_tipo')]
class TipoBus
{
    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 10, unique: true)]
    private string $alias;

    #[ORM\Column(type: 'text')]
    private string $descripcion;

    #[ORM\Column(type: 'boolean')]
    private bool $nivel2;

    #[ORM\Column(name: 'totalAsientos', type: 'integer')]
    private int $totalAsientos;

    #[ORM\Column(type: 'boolean')]
    private bool $activo;

    #[ORM\ManyToOne(targetEntity: ClaseBus::class)]
    #[ORM\JoinColumn(name: 'clase_id', referencedColumnName: 'id')]
    private ?ClaseBus $clase = null;

    #[ORM\OneToMany(mappedBy: 'tipoBus', targetEntity: AsientoBus::class)]
    private Collection $listaAsiento;

    public function __construct()
    {
        $this->listaAsiento = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getTotalAsientos(): int
    {
        return $this->totalAsientos;
    }

    public function getActivo(): bool
    {
        return $this->activo;
    }

    public function getListaAsiento(): Collection
    {
        return $this->listaAsiento;
    }
}
