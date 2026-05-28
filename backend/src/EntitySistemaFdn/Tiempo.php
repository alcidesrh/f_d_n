<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'tiempo')]
class Tiempo
{
    #[ORM\Id] #[ORM\Column(type: 'integer')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'integer')] private int $minutos;
    #[ORM\ManyToOne(targetEntity: Ruta::class)] #[ORM\JoinColumn(name: 'ruta_codigo', referencedColumnName: 'codigo')] private ?Ruta $ruta = null;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_destino_id', referencedColumnName: 'id')] private ?Estacion $estacionDestino = null;
    #[ORM\ManyToOne(targetEntity: ClaseBus::class)] #[ORM\JoinColumn(name: 'clasebus_id', referencedColumnName: 'id')] private ?ClaseBus $claseBus = null;
    public function getId(): ?int { return $this->id; }
}
