<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'reservacion')]
class Reservacion
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'boolean', nullable: true)] private ?bool $externa = null;
    #[ORM\Column(name: 'referencia_externa', type: 'string', length: 30, nullable: true)] private ?string $referenciaExterna = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $observacion = null;
    #[ORM\Column(name: 'fecha_creacion', type: 'datetime')] private \DateTime $fechaCreacion;
    #[ORM\Column(name: 'fecha_actualizacion', type: 'datetime', nullable: true)] private ?\DateTime $fechaActualizacion = null;
    #[ORM\ManyToOne(targetEntity: AsientoBus::class)] #[ORM\JoinColumn(name: 'asiento_bus_id', referencedColumnName: 'id')] private ?AsientoBus $asientoBus = null;
    #[ORM\ManyToOne(targetEntity: Cliente::class)] #[ORM\JoinColumn(name: 'cliente', referencedColumnName: 'id')] private ?Cliente $cliente = null;
    #[ORM\ManyToOne(targetEntity: Salida::class)] #[ORM\JoinColumn(name: 'salida_id', referencedColumnName: 'id')] private ?Salida $salida = null;
    #[ORM\ManyToOne(targetEntity: EstadoReservacion::class)] #[ORM\JoinColumn(name: 'estado_id', referencedColumnName: 'id')] private ?EstadoReservacion $estado = null;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_creacion_id', referencedColumnName: 'id')] private ?Estacion $estacionCreacion = null;
    public function getId(): ?int { return $this->id; }
}
