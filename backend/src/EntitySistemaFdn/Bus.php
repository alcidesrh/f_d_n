<?php

namespace App\EntitySistemaFdn;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'bus')]
class Bus
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 6)]
    private string $codigo;

    #[ORM\Column(type: 'string', length: 10, unique: true)]
    private string $placa;

    #[ORM\Column(name: 'anoFabricacion', type: 'integer')]
    private int $anoFabricacion;

    #[ORM\Column(name: 'numeroSeguro', type: 'string', length: 30, unique: true, nullable: true)]
    private ?string $numeroSeguro = null;

    #[ORM\Column(name: 'fechaVencimientoTarjetaOperaciones', type: 'date', nullable: true)]
    private ?\DateTime $fechaVencimientoTarjetaOperaciones = null;

    #[ORM\Column(name: 'numeroTarjetaRodaje', type: 'string', length: 50, nullable: true)]
    private ?string $numeroTarjetaRodaje = null;

    #[ORM\Column(name: 'numeroTarjetaOperaciones', type: 'string', length: 50, nullable: true)]
    private ?string $numeroTarjetaOperaciones = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descripcion = null;

    #[ORM\ManyToOne(targetEntity: TipoBus::class)]
    #[ORM\JoinColumn(name: 'tipo_id', referencedColumnName: 'id')]
    private ?TipoBus $tipo = null;

    #[ORM\ManyToOne(targetEntity: Empresa::class)]
    #[ORM\JoinColumn(name: 'empresa_id', referencedColumnName: 'id')]
    private ?Empresa $empresa = null;

    #[ORM\ManyToOne(targetEntity: MarcaBus::class)]
    #[ORM\JoinColumn(name: 'marca_id', referencedColumnName: 'id')]
    private ?MarcaBus $marca = null;

    #[ORM\ManyToOne(targetEntity: EstadoBus::class)]
    #[ORM\JoinColumn(name: 'estado_id', referencedColumnName: 'id')]
    private ?EstadoBus $estado = null;

    #[ORM\ManyToOne(targetEntity: Piloto::class)]
    #[ORM\JoinColumn(name: 'piloto_id', referencedColumnName: 'id')]
    private ?Piloto $piloto = null;

    #[ORM\ManyToOne(targetEntity: Piloto::class)]
    #[ORM\JoinColumn(name: 'piloto_aux_id', referencedColumnName: 'id')]
    private ?Piloto $pilotoAux = null;

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function getPlaca(): string
    {
        return $this->placa;
    }

    public function getTipo(): ?TipoBus
    {
        return $this->tipo;
    }

    public function getEmpresa(): ?Empresa
    {
        return $this->empresa;
    }

    public function getMarca(): ?MarcaBus
    {
        return $this->marca;
    }

    public function getEstado(): ?EstadoBus
    {
        return $this->estado;
    }

    public function getPiloto(): ?Piloto
    {
        return $this->piloto;
    }

    public function getPilotoAux(): ?Piloto
    {
        return $this->pilotoAux;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }
}
