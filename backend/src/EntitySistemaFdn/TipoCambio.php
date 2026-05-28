<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'tipo_cambio')]
class TipoCambio
{
    #[ORM\Id] #[ORM\Column(type: 'integer')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'date')] private \DateTime $fecha;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 8)] private string $tasa;
    #[ORM\ManyToOne(targetEntity: Moneda::class)] #[ORM\JoinColumn(name: 'moneda_id', referencedColumnName: 'id')] private ?Moneda $moneda = null;
    #[ORM\ManyToOne(targetEntity: TipoTipoCambio::class)] #[ORM\JoinColumn(name: 'tipo_tipo_cambio_id', referencedColumnName: 'id')] private ?TipoTipoCambio $tipoTipoCambio = null;
    public function getId(): ?int { return $this->id; }
}
