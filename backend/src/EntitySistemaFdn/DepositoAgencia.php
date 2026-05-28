<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'agencia_deposito')]
class DepositoAgencia
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'date')] private \DateTime $fecha;
    #[ORM\Column(type: 'decimal', precision: 7, scale: 2)] private string $importe;
    #[ORM\Column(name: 'numero_boleta', type: 'string', length: 15)] private string $numeroBoleta;
    #[ORM\Column(name: 'aplica_bono', type: 'boolean', nullable: true)] private ?bool $aplicaBono = null;
    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: true)] private ?string $bono = null;
    #[ORM\Column(type: 'string', length: 200, nullable: true)] private ?string $observacion = null;
    #[ORM\Column(name: 'motivo_rechazo', type: 'string', length: 100, nullable: true)] private ?string $motivoRechazo = null;
    #[ORM\Column(name: 'fecha_creacion', type: 'datetime')] private \DateTime $fechaCreacion;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_id', referencedColumnName: 'id')] private ?Estacion $estacion = null;
    #[ORM\ManyToOne(targetEntity: EstadoDeposito::class)] #[ORM\JoinColumn(name: 'estado_id', referencedColumnName: 'id')] private ?EstadoDeposito $estado = null;
    #[ORM\ManyToOne(targetEntity: Moneda::class)] #[ORM\JoinColumn(name: 'moneda_id', referencedColumnName: 'id')] private ?Moneda $moneda = null;
    public function getId(): ?int { return $this->id; }
}
