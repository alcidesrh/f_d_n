<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'tarifas_boleto')]
class TarifaBoleto
{
    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(name: 'fechaEfectividad', type: 'datetime')]
    private \DateTime $fechaEfectividad;

    #[ORM\Column(name: 'tarifaValor', type: 'decimal', precision: 7, scale: 2)]
    private string $tarifaValor;

    #[ORM\Column(name: 'fecha_creacion', type: 'datetime')]
    private \DateTime $fechaCreacion;

    #[ORM\ManyToOne(targetEntity: Estacion::class)]
    #[ORM\JoinColumn(name: 'estacion_origen_id', referencedColumnName: 'id')]
    private ?Estacion $estacionOrigen = null;

    #[ORM\ManyToOne(targetEntity: Estacion::class)]
    #[ORM\JoinColumn(name: 'estacion_destino_id', referencedColumnName: 'id')]
    private ?Estacion $estacionDestino = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTarifaValor(): string
    {
        return $this->tarifaValor;
    }

    public function getEstacionOrigen(): ?Estacion
    {
        return $this->estacionOrigen;
    }

    public function getEstacionDestino(): ?Estacion
    {
        return $this->estacionDestino;
    }

    public function getFechaEfectividad(): \DateTime
    {
        return $this->fechaEfectividad;
    }
}
