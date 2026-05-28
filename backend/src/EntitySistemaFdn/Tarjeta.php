<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'tarjeta')]
class Tarjeta
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'bigint')] private int $numero;
    #[ORM\Column(name: 'fecha_conciliacion', type: 'datetime', nullable: true)] private ?\DateTime $fechaConciliacion = null;
    #[ORM\Column(name: 'observacion_conciliacion', type: 'text', nullable: true)] private ?string $observacionConciliacion = null;
    #[ORM\Column(name: 'fecha_creacion', type: 'datetime')] private \DateTime $fechaCreacion;
    #[ORM\ManyToOne(targetEntity: Salida::class)] #[ORM\JoinColumn(name: 'salida_id', referencedColumnName: 'id')] private ?Salida $salida = null;
    #[ORM\ManyToOne(targetEntity: EstadoTarjeta::class)] #[ORM\JoinColumn(name: 'estado_id', referencedColumnName: 'id')] private ?EstadoTarjeta $estado = null;
    #[ORM\ManyToOne(targetEntity: TipoTarjeta::class)] #[ORM\JoinColumn(name: 'tipo_id', referencedColumnName: 'id')] private ?TipoTarjeta $tipo = null;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_creacion_id', referencedColumnName: 'id')] private ?Estacion $estacionCreacion = null;
    public function getId(): ?int { return $this->id; }
}
