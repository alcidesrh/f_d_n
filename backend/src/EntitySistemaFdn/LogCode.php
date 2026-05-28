<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'custom_log_code')]
class LogCode
{
    #[ORM\Id] #[ORM\Column(type: 'string', length: 6)] private string $codigo;
    #[ORM\Column(type: 'text')] private string $descripcion;
    public function getCodigo(): string { return $this->codigo; }
}
