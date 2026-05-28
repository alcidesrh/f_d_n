<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'tarifas_encomienda')]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'tipoTarifa', type: 'integer')]
#[ORM\DiscriminatorMap([1 => TarifaEncomiendaEfectivo::class, 2 => TarifaEncomiendaEspeciales::class, 3 => TarifaEncomiendaPaquetesPeso::class, 4 => TarifaEncomiendaPaquetesVolumen::class, 5 => TarifaEncomiendaDistancia::class])]
abstract class TarifaEncomienda
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(name: 'fecha_creacion', type: 'datetime')] private \DateTime $fechaCreacion;
    public function getId(): ?int { return $this->id; }
}
