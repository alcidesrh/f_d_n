<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'tarifas_encomienda_especiales')]
class TarifaEncomiendaEspeciales extends TarifaEncomienda
{
    #[ORM\Column(name: 'fecha_efectividad', type: 'datetime')] private \DateTime $fechaEfectividad;
    #[ORM\Column(name: 'tarifa_valor', type: 'decimal', precision: 7, scale: 2)] private string $tarifaValor;
    #[ORM\ManyToOne(targetEntity: TipoEncomiendaEspeciales::class)] #[ORM\JoinColumn(name: 'tipo_encomienda_especial_id', referencedColumnName: 'id')] private ?TipoEncomiendaEspeciales $tipoEncomiendaEspecial = null;
}
