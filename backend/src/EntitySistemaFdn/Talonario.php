<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'talonario')]
class Talonario
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'bigint')] private int $inicial;
    #[ORM\Column(type: 'bigint')] private int $final;
    #[ORM\Column(name: 'fecha_creacion', type: 'datetime')] private \DateTime $fechaCreacion;
    #[ORM\ManyToOne(targetEntity: Tarjeta::class)] #[ORM\JoinColumn(name: 'tarjeta_id', referencedColumnName: 'id')] private ?Tarjeta $tarjeta = null;
    public function getId(): ?int { return $this->id; }
}
