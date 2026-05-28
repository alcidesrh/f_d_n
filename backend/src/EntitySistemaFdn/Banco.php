<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'banco')]
class Banco
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'string', length: 40, unique: true)] private string $alias;
    #[ORM\Column(type: 'string', length: 100)] private string $nombre;
    #[ORM\Column(type: 'string', length: 15, nullable: true)] private ?string $telefono = null;
    #[ORM\Column(type: 'string', length: 200, nullable: true)] private ?string $direccion = null;
    #[ORM\Column(type: 'boolean')] private bool $activo;
    public function getId(): ?int { return $this->id; }
}
