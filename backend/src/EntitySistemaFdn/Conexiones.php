<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'conexiones')]
class Conexiones
{
    #[ORM\Id] #[ORM\Column(type: 'string', length: 6)] private string $codigo;
    #[ORM\Column(type: 'string', length: 100)] private string $nombre;
    #[ORM\Column(type: 'string', length: 50)] private string $horario;
    #[ORM\Column(type: 'string', length: 50)] private string $precio;
    #[ORM\Column(type: 'string', length: 500)] private string $descripcion;
    #[ORM\Column(type: 'boolean')] private bool $activo;
    public function getCodigo(): string { return $this->codigo; }
}
