<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'custom_rol')]
class Rol
{
    #[ORM\Id] #[ORM\Column(type: 'string', length: 255)] private string $nombre;
    #[ORM\Column(type: 'string', length: 255, nullable: true)] private ?string $descripcion = null;
    public function getNombre(): string { return $this->nombre; }
}
