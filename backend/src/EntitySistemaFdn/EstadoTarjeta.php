<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'tarjeta_estado')]
class EstadoTarjeta
{
    #[ORM\Id] #[ORM\Column(type: 'smallint')] private ?int $id = null;
    #[ORM\Column(type: 'string', length: 40, unique: true)] private string $nombre;
    public function getId(): ?int { return $this->id; }
}
