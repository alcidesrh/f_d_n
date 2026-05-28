<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'galeria_imagen')]
class Imagen
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'string', length: 255, nullable: true)] private ?string $descripcion = null;
    #[ORM\Column(name: 'imagen_normal', type: 'text', nullable: true)] private ?string $imagenNormal = null;
    #[ORM\Column(name: 'imagen_pequena', type: 'text', nullable: true)] private ?string $imagenPequena = null;
    #[ORM\Column(type: 'string', length: 10)] private string $formato;
    #[ORM\ManyToOne(targetEntity: Galeria::class)] #[ORM\JoinColumn(name: 'galeria_id', referencedColumnName: 'id')] private ?Galeria $galeria = null;
    public function getId(): ?int { return $this->id; }
}
