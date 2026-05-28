<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'log')]
class Log
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'string', length: 255)] private string $address;
    #[ORM\Column(type: 'string', length: 255)] private string $tipo;
    #[ORM\Column(type: 'datetime')] private \DateTime $fecha;
    public function getId(): ?int { return $this->id; }
}
