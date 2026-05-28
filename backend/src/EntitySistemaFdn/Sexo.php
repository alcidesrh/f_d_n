<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'sexo')]
class Sexo
{
    #[ORM\Id] #[ORM\Column(type: 'smallint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'string', length: 1)] private string $sigla;
    #[ORM\Column(type: 'string', length: 40)] private string $nombre;
    public function getId(): ?int { return $this->id; }
}
