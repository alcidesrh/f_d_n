<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'factura_generada')]
class FacturaGenerada
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(type: 'bigint', nullable: true)] private ?int $consecutivo = null;
    #[ORM\Column(name: 'importe_total', type: 'decimal', precision: 10, scale: 2)] private string $importeTotal;
    #[ORM\Column(type: 'datetime')] private \DateTime $fecha;
    #[ORM\Column(name: 'autorizacion_tarjeta', type: 'string', length: 20, nullable: true)] private ?string $autorizacionTarjeta = null;
    #[ORM\Column(name: 'referencia_externa', type: 'string', length: 20, nullable: true)] private ?string $referenciaExterna = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $observacion = null;
    #[ORM\Column(name: 'fecha_creacion', type: 'datetime', nullable: true)] private ?\DateTime $fechaCreacion = null;
    #[ORM\Column(name: 'fecha_anulacion', type: 'datetime', nullable: true)] private ?\DateTime $fechaAnulacion = null;
    #[ORM\Column(name: 's_autorizacion_uuid_sat', type: 'string', length: 255, nullable: true)] private ?string $sAutorizacionUUIDsat = null;
    #[ORM\Column(name: 's_numero_dte_sat', type: 'bigint', nullable: true)] private ?int $sNumeroDTEsat = null;
    #[ORM\Column(name: 's_serie_dte_sat', type: 'string', length: 255, nullable: true)] private ?string $sSerieDTEsat = null;
    #[ORM\Column(name: 's_fecha_certifica_dte_sat', type: 'datetime', nullable: true)] private ?\DateTime $sFechaCertificaDTEsat = null;
    #[ORM\ManyToOne(targetEntity: Factura::class)] #[ORM\JoinColumn(name: 'factura_id', referencedColumnName: 'id')] private ?Factura $factura = null;
    #[ORM\ManyToOne(targetEntity: ServicioEstacion::class)] #[ORM\JoinColumn(name: 'servicio_estacion_id', referencedColumnName: 'id')] private ?ServicioEstacion $servicioEstacion = null;
    #[ORM\ManyToOne(targetEntity: Moneda::class)] #[ORM\JoinColumn(name: 'moneda_id', referencedColumnName: 'id')] private ?Moneda $moneda = null;
    #[ORM\ManyToOne(targetEntity: TipoCambio::class)] #[ORM\JoinColumn(name: 'tipo_cambio_id', referencedColumnName: 'id')] private ?TipoCambio $tipoCambio = null;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_id', referencedColumnName: 'id')] private ?Estacion $estacion = null;
    public function getId(): ?int { return $this->id; }
}
