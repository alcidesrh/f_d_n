<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'estacion_telefono')]
class TelefonoEstacion
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'string', length: 25, unique: true)] private string $telefono;
    #[ORM\Column(type: 'boolean')] private bool $activo;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_id', referencedColumnName: 'id')] private ?Estacion $estacion = null;
    public function getId(): ?int { return $this->id; }
}
