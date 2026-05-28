<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'encomienda_ruta')]
class EncomiendaRuta
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'integer')] private int $posicion;
    #[ORM\ManyToOne(targetEntity: Encomienda::class)] #[ORM\JoinColumn(name: 'encomienda_id', referencedColumnName: 'id')] private ?Encomienda $encomienda = null;
    #[ORM\ManyToOne(targetEntity: Ruta::class)] #[ORM\JoinColumn(name: 'ruta_codigo', referencedColumnName: 'codigo')] private ?Ruta $ruta = null;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion', referencedColumnName: 'id')] private ?Estacion $estacion = null;
    public function getId(): ?int { return $this->id; }
}
