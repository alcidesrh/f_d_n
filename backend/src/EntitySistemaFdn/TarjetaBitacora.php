<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'tarjeta_bitacora')]
class TarjetaBitacora
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'datetime')] private \DateTime $fecha;
    #[ORM\Column(type: 'string', length: 255)] private string $descripcion;
    #[ORM\ManyToOne(targetEntity: Tarjeta::class)] #[ORM\JoinColumn(name: 'tarjeta_id', referencedColumnName: 'id')] private ?Tarjeta $tarjeta = null;
    public function getId(): ?int { return $this->id; }
}
