<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'tarifas_encomienda_paquetes_peso')]
class TarifaEncomiendaPaquetesPeso extends TarifaEncomienda
{
    #[ORM\Column(name: 'peso_minimo', type: 'decimal', precision: 7, scale: 2, nullable: true)] private ?string $pesoMinimo = null;
    #[ORM\Column(name: 'peso_maximo', type: 'decimal', precision: 7, scale: 2, nullable: true)] private ?string $pesoMaximo = null;
    #[ORM\Column(name: 'fecha_efectividad', type: 'datetime')] private \DateTime $fechaEfectividad;
    #[ORM\Column(name: 'tarifa_porcentual', type: 'boolean')] private bool $tarifaPorcentual;
    #[ORM\Column(name: 'tarifa_valor', type: 'decimal', precision: 10, scale: 5)] private string $tarifaValor;
    #[ORM\Column(name: 'tarifa_porcentual_valor_minimo', type: 'decimal', precision: 7, scale: 2, nullable: true)] private ?string $tarifaPorcentualValorMinimo = null;
    #[ORM\Column(name: 'tarifa_porcentual_valor_maximo', type: 'decimal', precision: 7, scale: 2, nullable: true)] private ?string $tarifaPorcentualValorMaximo = null;
}
