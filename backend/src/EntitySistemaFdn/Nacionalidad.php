<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'nacionalidad')]
class Nacionalidad
{
    #[ORM\Id]
    #[ORM\Column(type: 'smallint')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;
    #[ORM\Column(type: 'string', length: 3, nullable: true)] private ?string $sigla = null;
    #[ORM\Column(type: 'string', length: 50, unique: true)] private string $nombre;
    #[ORM\Column(type: 'boolean')] private bool $activo;
    public function getId(): ?int { return $this->id; }
}
