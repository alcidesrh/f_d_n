<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'autorizacion_operacion')]
class AutorizacionOperacion
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'string', length: 150)] private string $motivo;
    #[ORM\Column(type: 'string', length: 150, nullable: true)] private ?string $observacion = null;
    #[ORM\Column(name: 'fecha_creacion', type: 'datetime')] private \DateTime $fechaCreacion;
    #[ORM\Column(name: 'fecha_actualizacion', type: 'datetime', nullable: true)] private ?\DateTime $fechaActualizacion = null;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_id', referencedColumnName: 'id')] private ?Estacion $estacion = null;
    #[ORM\ManyToOne(targetEntity: Boleto::class)] #[ORM\JoinColumn(name: 'boleto_id', referencedColumnName: 'id')] private ?Boleto $boleto = null;
    #[ORM\ManyToOne(targetEntity: TipoAutorizacionOperacion::class)] #[ORM\JoinColumn(name: 'tipo_id', referencedColumnName: 'id')] private ?TipoAutorizacionOperacion $tipo = null;
    #[ORM\ManyToOne(targetEntity: EstadoAutorizacionOperacion::class)] #[ORM\JoinColumn(name: 'estado_id', referencedColumnName: 'id')] private ?EstadoAutorizacionOperacion $estado = null;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_creacion_id', referencedColumnName: 'id')] private ?Estacion $estacionCreacion = null;
    public function getId(): ?int { return $this->id; }
}
