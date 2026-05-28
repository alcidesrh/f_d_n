<?php

namespace App\EntitySistemaFdn;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'salida')]
class Salida
{
    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $fecha;

    #[ORM\Column(type: 'boolean')]
    private bool $reasignado;

    #[ORM\Column(name: 'motivoReasignado', type: 'text', nullable: true)]
    private ?string $motivoReasignado = null;

    #[ORM\Column(name: 'cancelacion_interna', type: 'boolean')]
    private bool $cancelacionInterna = false;

    #[ORM\ManyToOne(targetEntity: Itinerario::class)]
    #[ORM\JoinColumn(name: 'itinerario_id', referencedColumnName: 'id')]
    private ?Itinerario $itinerario = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(name: 'empresa_id', referencedColumnName: 'id')]
    private ?Empresa $empresa = null;

    #[ORM\ManyToOne(targetEntity: TipoBus::class)]
    #[ORM\JoinColumn(name: 'tipo_bus_id', referencedColumnName: 'id')]
    private ?TipoBus $tipoBus = null;

    #[ORM\ManyToOne(targetEntity: EstadoSalida::class)]
    #[ORM\JoinColumn(name: 'estado_id', referencedColumnName: 'id')]
    private ?EstadoSalida $estado = null;

    #[ORM\ManyToOne(targetEntity: Bus::class)]
    #[ORM\JoinColumn(name: 'bus_codigo', referencedColumnName: 'codigo')]
    private ?Bus $bus = null;

    #[ORM\ManyToOne(targetEntity: Piloto::class)]
    #[ORM\JoinColumn(name: 'piloto_id', referencedColumnName: 'id')]
    private ?Piloto $piloto = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFecha(): \DateTime
    {
        return $this->fecha;
    }

    public function getItinerario(): ?Itinerario
    {
        return $this->itinerario;
    }

    public function getEmpresa(): ?Empresa
    {
        return $this->empresa;
    }

    public function getTipoBus(): ?TipoBus
    {
        return $this->tipoBus;
    }

    public function getEstado(): ?EstadoSalida
    {
        return $this->estado;
    }

    public function getBus(): ?Bus
    {
        return $this->bus;
    }

    public function getPiloto(): ?Piloto
    {
        return $this->piloto;
    }

    public function getReasignado(): bool
    {
        return $this->reasignado;
    }
}
