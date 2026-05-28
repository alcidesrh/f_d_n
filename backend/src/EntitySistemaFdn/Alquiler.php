<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'alquiler')]
class Alquiler
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(name: 'fecha_inicial', type: 'datetime')] private \DateTime $fechaInicial;
    #[ORM\Column(name: 'fecha_final', type: 'datetime')] private \DateTime $fechaFinal;
    #[ORM\Column(type: 'decimal', precision: 7, scale: 2)] private string $importe;
    #[ORM\Column(type: 'text')] private string $observacion;
    #[ORM\Column(name: 'fecha_creacion', type: 'datetime')] private \DateTime $fechaCreacion;
    #[ORM\Column(name: 'fecha_efectuado', type: 'datetime', nullable: true)] private ?\DateTime $fechaEfectuado = null;
    #[ORM\Column(name: 'fecha_cancelado', type: 'datetime', nullable: true)] private ?\DateTime $fechaCancelado = null;
    #[ORM\ManyToOne(targetEntity: Empresa::class)] #[ORM\JoinColumn(name: 'empresa_id', referencedColumnName: 'id')] private ?Empresa $empresa = null;
    #[ORM\ManyToOne(targetEntity: Bus::class)] #[ORM\JoinColumn(name: 'bus_codigo', referencedColumnName: 'codigo')] private ?Bus $bus = null;
    #[ORM\ManyToOne(targetEntity: Piloto::class)] #[ORM\JoinColumn(name: 'piloto_id', referencedColumnName: 'id')] private ?Piloto $piloto = null;
    #[ORM\ManyToOne(targetEntity: Piloto::class)] #[ORM\JoinColumn(name: 'piloto_aux_id', referencedColumnName: 'id')] private ?Piloto $pilotoAux = null;
    #[ORM\ManyToOne(targetEntity: EstadoAlquiler::class)] #[ORM\JoinColumn(name: 'estado_id', referencedColumnName: 'id')] private ?EstadoAlquiler $estado = null;
    public function getId(): ?int { return $this->id; }
}
