<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'notificacion')]
class Notificacion
{
    #[ORM\Id] #[ORM\Column(type: 'integer')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'string', length: 300)] private string $texto;
    #[ORM\Column(type: 'integer')] private int $segundos;
    #[ORM\Column(type: 'boolean')] private bool $oficinas;
    #[ORM\Column(type: 'boolean')] private bool $agencias;
    #[ORM\Column(type: 'boolean')] private bool $activo;
    public function getId(): ?int { return $this->id; }
}
