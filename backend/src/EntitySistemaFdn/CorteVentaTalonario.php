<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'talonario_corte_venta')]
class CorteVentaTalonario
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'bigint')] private int $inicial;
    #[ORM\Column(type: 'bigint')] private int $final;
    #[ORM\Column(name: 'importe_total', type: 'decimal', precision: 10, scale: 2)] private string $importeTotal;
    #[ORM\Column(name: 'importe_total_items', type: 'decimal', precision: 10, scale: 2)] private string $importeTotalItems;
    #[ORM\Column(type: 'date')] private \DateTime $fecha;
    #[ORM\Column(name: 'fecha_creacion', type: 'datetime')] private \DateTime $fechaCreacion;
    #[ORM\ManyToOne(targetEntity: Talonario::class)] #[ORM\JoinColumn(name: 'talonario_id', referencedColumnName: 'id')] private ?Talonario $talonario = null;
    #[ORM\ManyToOne(targetEntity: EstadoCorteVentaTalonario::class)] #[ORM\JoinColumn(name: 'estado_id', referencedColumnName: 'id')] private ?EstadoCorteVentaTalonario $estado = null;
    public function getId(): ?int { return $this->id; }
}
