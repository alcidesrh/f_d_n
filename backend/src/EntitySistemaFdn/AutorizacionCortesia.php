<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'autorizacion_cortesia')]
class AutorizacionCortesia
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'text')] private string $motivo;
    #[ORM\Column(type: 'string', length: 20, unique: true)] private string $codigo;
    #[ORM\Column(name: 'notificar_cliente', type: 'boolean')] private bool $notificarCliente;
    #[ORM\Column(name: 'fecha_creacion', type: 'datetime')] private \DateTime $fechaCreacion;
    #[ORM\Column(name: 'fecha_utilizacion', type: 'datetime', nullable: true)] private ?\DateTime $fechaUtilizacion = null;
    #[ORM\Column(name: 'restriccion_fecha_uso', type: 'date', nullable: true)] private ?\DateTime $restriccionFechaUso = null;
    #[ORM\Column(type: 'boolean')] private bool $activo;
    #[ORM\ManyToOne(targetEntity: ServicioEstacion::class)] #[ORM\JoinColumn(name: 'servicio_estacion', referencedColumnName: 'id')] private ?ServicioEstacion $servicioEstacion = null;
    #[ORM\ManyToOne(targetEntity: ClaseAsiento::class)] #[ORM\JoinColumn(name: 'restriccion_clase_asiento', referencedColumnName: 'id')] private ?ClaseAsiento $restriccionClaseAsiento = null;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'restriccion_estacion_origen_id', referencedColumnName: 'id')] private ?Estacion $restriccionEstacionOrigen = null;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'restriccion_estacion_destino_id', referencedColumnName: 'id')] private ?Estacion $restriccionEstacionDestino = null;
    #[ORM\ManyToOne(targetEntity: Cliente::class)] #[ORM\JoinColumn(name: 'restriccion_cliente', referencedColumnName: 'id')] private ?Cliente $restriccionCliente = null;
    #[ORM\ManyToOne(targetEntity: Salida::class)] #[ORM\JoinColumn(name: 'restriccion_salida_id', referencedColumnName: 'id')] private ?Salida $restriccionSalida = null;
    #[ORM\ManyToOne(targetEntity: AsientoBus::class)] #[ORM\JoinColumn(name: 'restriccion_asiento_bus_id', referencedColumnName: 'id')] private ?AsientoBus $restriccionAsientoBus = null;
    public function getId(): ?int { return $this->id; }
}
