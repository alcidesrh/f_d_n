<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'itineario')]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'tipoItinerario', type: 'integer')]
#[ORM\DiscriminatorMap([1 => ItinerarioCiclico::class, 2 => ItinerarioEspecial::class])]
class Itinerario
{
    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: 'boolean')]
    private bool $activo;

    #[ORM\ManyToOne(targetEntity: Ruta::class)]
    #[ORM\JoinColumn(name: 'ruta_codigo', referencedColumnName: 'codigo')]
    private ?Ruta $ruta = null;

    #[ORM\ManyToOne(targetEntity: TipoBus::class)]
    #[ORM\JoinColumn(name: 'tipo_bus_id', referencedColumnName: 'id')]
    private ?TipoBus $tipoBus = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(name: 'empresa_id', referencedColumnName: 'id')]
    private ?Empresa $empresa = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRuta(): ?Ruta
    {
        return $this->ruta;
    }

    public function getTipoBus(): ?TipoBus
    {
        return $this->tipoBus;
    }

    public function getEmpresa(): ?Empresa
    {
        return $this->empresa;
    }

    public function getActivo(): bool
    {
        return $this->activo;
    }
}
