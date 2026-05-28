<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'boleto_bitacora')]
class BoletoBitacora
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'datetime')] private \DateTime $fecha;
    #[ORM\Column(type: 'string', length: 255)] private string $descripcion;
    #[ORM\ManyToOne(targetEntity: Boleto::class)] #[ORM\JoinColumn(name: 'boleto_id', referencedColumnName: 'id')] private ?Boleto $boleto = null;
    #[ORM\ManyToOne(targetEntity: EstadoBoleto::class)] #[ORM\JoinColumn(name: 'estado_id', referencedColumnName: 'id')] private ?EstadoBoleto $estado = null;
    public function getId(): ?int { return $this->id; }
}
