<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'tarifas_encomienda_efectivo')]
class TarifaEncomiendaEfectivo extends TarifaEncomienda
{
    #[ORM\Column(name: 'importe_minimo', type: 'decimal', precision: 7, scale: 2, nullable: true)] private ?string $importeMinimo = null;
    #[ORM\Column(name: 'importe_maximo', type: 'decimal', precision: 7, scale: 2, nullable: true)] private ?string $importeMaximo = null;
    #[ORM\Column(name: 'fecha_efectividad', type: 'datetime')] private \DateTime $fechaEfectividad;
    #[ORM\Column(name: 'tarifa_porcentual', type: 'boolean')] private bool $tarifaPorcentual;
    #[ORM\Column(name: 'tarifa_valor', type: 'decimal', precision: 7, scale: 2)] private string $tarifaValor;
    #[ORM\Column(name: 'tarifa_porcentual_valor_minimo', type: 'decimal', precision: 7, scale: 2, nullable: true)] private ?string $tarifaPorcentualValorMinimo = null;
    #[ORM\Column(name: 'tarifa_porcentual_valor_maximo', type: 'decimal', precision: 7, scale: 2, nullable: true)] private ?string $tarifaPorcentualValorMaximo = null;
}
