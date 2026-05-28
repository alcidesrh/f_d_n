<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'sistema')]
class Sistema
{
    #[ORM\Id] #[ORM\Column(type: 'string', length: 40)] private string $codigo;
    #[ORM\Id] #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_id', referencedColumnName: 'id')] private ?Estacion $estacion = null;
    #[ORM\Column(type: 'string', length: 100)] private string $valor;
    public function getCodigo(): string { return $this->codigo; }
    public function getEstacion(): ?Estacion { return $this->estacion; }
}
