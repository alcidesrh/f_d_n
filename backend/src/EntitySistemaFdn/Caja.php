<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'caja')]
class Caja
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(name: 'fecha_creacion', type: 'datetime')] private \DateTime $fechaCreacion;
    #[ORM\Column(name: 'fecha_apertura', type: 'datetime', nullable: true)] private ?\DateTime $fechaApertura = null;
    #[ORM\Column(name: 'fecha_cierre', type: 'datetime', nullable: true)] private ?\DateTime $fechaCierre = null;
    #[ORM\Column(name: 'fecha_cancelacion', type: 'datetime', nullable: true)] private ?\DateTime $fechaCancelacion = null;
    #[ORM\ManyToOne(targetEntity: Moneda::class)] #[ORM\JoinColumn(name: 'moneda_id', referencedColumnName: 'id')] private ?Moneda $moneda = null;
    #[ORM\ManyToOne(targetEntity: EstadoCaja::class)] #[ORM\JoinColumn(name: 'estado_id', referencedColumnName: 'id')] private ?EstadoCaja $estado = null;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_id', referencedColumnName: 'id')] private ?Estacion $estacion = null;
    #[ORM\ManyToOne(targetEntity: User::class)] #[ORM\JoinColumn(name: 'usuario_id', referencedColumnName: 'id')] private ?User $usuario = null;
    public function getId(): ?int { return $this->id; }
}
