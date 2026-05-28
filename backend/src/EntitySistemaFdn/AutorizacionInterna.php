<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'autorizacion_interna')]
class AutorizacionInterna
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'text')] private string $motivo;
    #[ORM\Column(type: 'string', length: 20, unique: true)] private string $codigo;
    #[ORM\Column(name: 'fecha_creacion', type: 'datetime')] private \DateTime $fechaCreacion;
    #[ORM\Column(name: 'fecha_utilizacion', type: 'datetime', nullable: true)] private ?\DateTime $fechaUtilizacion = null;
    #[ORM\Column(type: 'boolean')] private bool $activo;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_origen_id', referencedColumnName: 'id')] private ?Estacion $estacionOrigen = null;
    public function getId(): ?int { return $this->id; }
}
