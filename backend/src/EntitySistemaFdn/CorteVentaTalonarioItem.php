<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'talonario_corte_venta_item')]
class CorteVentaTalonarioItem
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'bigint')] private int $numero;
    #[ORM\Column(type: 'decimal', precision: 7, scale: 2)] private string $importe;
    #[ORM\Column(name: 'fecha_creacion', type: 'datetime')] private \DateTime $fechaCreacion;
    #[ORM\Column(name: 'fecha_actualizacion', type: 'datetime', nullable: true)] private ?\DateTime $fechaActualizacion = null;
    #[ORM\ManyToOne(targetEntity: CorteVentaTalonario::class)] #[ORM\JoinColumn(name: 'corte_venta_talonario', referencedColumnName: 'id')] private ?CorteVentaTalonario $corteVentaTalonario = null;
    public function getId(): ?int { return $this->id; }
}
