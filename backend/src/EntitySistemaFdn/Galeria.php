<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'galeria')]
class Galeria
{
    #[ORM\Id] #[ORM\Column(type: 'integer')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'integer')] private int $orden;
    #[ORM\Column(type: 'string', length: 100)] private string $nombre;
    #[ORM\Column(type: 'string', length: 255, nullable: true)] private ?string $descripcion = null;
    #[ORM\Column(type: 'boolean')] private bool $activo;
    public function getId(): ?int { return $this->id; }
}
