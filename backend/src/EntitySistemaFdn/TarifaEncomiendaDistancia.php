<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'tarifas_encomienda_distancia')]
class TarifaEncomiendaDistancia extends TarifaEncomienda
{
    #[ORM\Column(name: 'fecha_efectividad', type: 'datetime')] private \DateTime $fechaEfectividad;
    #[ORM\Column(name: 'tarifa_valor', type: 'decimal', precision: 7, scale: 2)] private string $tarifaValor;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_origen_id', referencedColumnName: 'id')] private ?Estacion $estacionOrigen = null;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_destino_id', referencedColumnName: 'id')] private ?Estacion $estacionDestino = null;
}
