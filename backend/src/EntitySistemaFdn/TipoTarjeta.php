<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'tipo_tarjeta')]
class TipoTarjeta
{
    #[ORM\Id] #[ORM\Column(type: 'smallint')] private ?int $id = null;
    #[ORM\Column(type: 'string', length: 3, unique: true)] private string $sigla;
    #[ORM\Column(type: 'string', length: 50, unique: true)] private string $nombre;
    public function getId(): ?int { return $this->id; }
}
