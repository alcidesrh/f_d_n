<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'encomienda_tipo')]
class TipoEncomienda
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'string', length: 40, unique: true)] private string $nombre;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $descripcion = null;
    #[ORM\Column(type: 'boolean')] private bool $activo;
    public function getId(): ?int { return $this->id; }
}
