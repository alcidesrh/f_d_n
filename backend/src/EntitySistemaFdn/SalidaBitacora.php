<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'salida_bitacora')]
class SalidaBitacora
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'datetime')] private \DateTime $fecha;
    #[ORM\Column(type: 'string', length: 255)] private string $descripcion;
    #[ORM\ManyToOne(targetEntity: Salida::class)] #[ORM\JoinColumn(name: 'salida_id', referencedColumnName: 'id')] private ?Salida $salida = null;
    #[ORM\ManyToOne(targetEntity: EstadoSalida::class)] #[ORM\JoinColumn(name: 'estado_id', referencedColumnName: 'id')] private ?EstadoSalida $estado = null;
    public function getId(): ?int { return $this->id; }
}
