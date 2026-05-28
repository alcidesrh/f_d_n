<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'itineario_especial')]
class ItinerarioEspecial extends Itinerario
{
    #[ORM\Column(type: 'datetime')]
    private \DateTime $fecha;

    #[ORM\Column(type: 'text')]
    private string $motivo;

    #[ORM\ManyToOne(targetEntity: Estacion::class)]
    #[ORM\JoinColumn(name: 'estacion_origen_id', referencedColumnName: 'id')]
    private ?Estacion $estacionOrigen = null;

    public function getFecha(): \DateTime
    {
        return $this->fecha;
    }

    public function getMotivo(): string
    {
        return $this->motivo;
    }

    public function getEstacionOrigen(): ?Estacion
    {
        return $this->estacionOrigen;
    }
}
