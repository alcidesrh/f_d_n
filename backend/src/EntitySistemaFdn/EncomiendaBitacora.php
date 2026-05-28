<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'encomienda_bitacora')]
class EncomiendaBitacora
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'datetime')] private \DateTime $fecha;
    #[ORM\ManyToOne(targetEntity: Encomienda::class)] #[ORM\JoinColumn(name: 'encomienda_id', referencedColumnName: 'id')] private ?Encomienda $encomienda = null;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion', referencedColumnName: 'id')] private ?Estacion $estacion = null;
    #[ORM\ManyToOne(targetEntity: EstadoEncomienda::class)] #[ORM\JoinColumn(name: 'estado_id', referencedColumnName: 'id')] private ?EstadoEncomienda $estado = null;
    #[ORM\ManyToOne(targetEntity: Salida::class)] #[ORM\JoinColumn(name: 'salida_id', referencedColumnName: 'id')] private ?Salida $salida = null;
    #[ORM\ManyToOne(targetEntity: Cliente::class)] #[ORM\JoinColumn(name: 'cliente_id', referencedColumnName: 'id')] private ?Cliente $cliente = null;
    public function getId(): ?int { return $this->id; }
}
