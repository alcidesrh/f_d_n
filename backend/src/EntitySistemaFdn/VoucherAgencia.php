<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'boleto_voucher_agencia')]
class VoucherAgencia
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'boolean')] private bool $bono;
    #[ORM\Column(name: 'importe_total', type: 'decimal', precision: 10, scale: 2)] private string $importeTotal;
    #[ORM\Column(type: 'datetime')] private \DateTime $fecha;
    #[ORM\Column(name: 'referencia_externa', type: 'string', length: 20, nullable: true)] private ?string $referenciaExterna = null;
    #[ORM\Column(name: 'fecha_anulacion', type: 'datetime', nullable: true)] private ?\DateTime $fechaAnulacion = null;
    #[ORM\ManyToOne(targetEntity: Empresa::class)] #[ORM\JoinColumn(name: 'empresa_id', referencedColumnName: 'id')] private ?Empresa $empresa = null;
    #[ORM\ManyToOne(targetEntity: Moneda::class)] #[ORM\JoinColumn(name: 'moneda_id', referencedColumnName: 'id')] private ?Moneda $moneda = null;
    #[ORM\ManyToOne(targetEntity: TipoCambio::class)] #[ORM\JoinColumn(name: 'tipo_cambio_id', referencedColumnName: 'id')] private ?TipoCambio $tipoCambio = null;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_id', referencedColumnName: 'id')] private ?Estacion $estacion = null;
    public function getId(): ?int { return $this->id; }
}
