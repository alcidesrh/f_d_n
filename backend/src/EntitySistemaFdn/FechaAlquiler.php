<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'alquiler_fecha')]
class FechaAlquiler
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'datetime')] private \DateTime $fecha;
    #[ORM\ManyToOne(targetEntity: Alquiler::class)] #[ORM\JoinColumn(name: 'alquiler_id', referencedColumnName: 'id')] private ?Alquiler $alquiler = null;
    public function getId(): ?int { return $this->id; }
}
