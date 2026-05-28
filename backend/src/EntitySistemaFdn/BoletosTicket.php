<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'boletos_ticket')]
class BoletosTicket
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(name: 'identificador_ticket', type: 'bigint')] private int $identificadorTicket;
    #[ORM\Column(type: 'array')] private array $boletos;
    #[ORM\Column(name: 'fecha_creacion', type: 'datetime', nullable: true)] private ?\DateTime $fechaCreacion = null;
    public function getId(): ?int { return $this->id; }
}
