<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'encomienda')]
class Encomienda
{
    #[ORM\Id] #[ORM\Column(type: 'bigint')] #[ORM\GeneratedValue(strategy: 'AUTO')] private ?int $id = null;
    #[ORM\Column(name: 'identificador_web', type: 'string', length: 80, nullable: true)] private ?string $identificadorWeb = null;
    #[ORM\Column(type: 'integer')] private int $cantidad;
    #[ORM\Column(name: 'valor_declarado_porciento', type: 'decimal', precision: 5, scale: 2, nullable: true)] private ?string $valorDeclaradoPorciento = null;
    #[ORM\Column(name: 'valor_declarado', type: 'integer', nullable: true)] private ?int $valorDeclarado = null;
    #[ORM\Column(type: 'decimal', precision: 5, scale: 0, nullable: true)] private ?string $peso = null;
    #[ORM\Column(type: 'decimal', precision: 5, scale: 0, nullable: true)] private ?string $alto = null;
    #[ORM\Column(type: 'decimal', precision: 5, scale: 0, nullable: true)] private ?string $ancho = null;
    #[ORM\Column(type: 'decimal', precision: 5, scale: 0, nullable: true)] private ?string $profundidad = null;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 0, nullable: true)] private ?string $volumen = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $descripcion = null;
    #[ORM\Column(name: 'precio_calculado_moneda_base', type: 'decimal', precision: 7, scale: 2, nullable: true)] private ?string $precioCalculadoMonedaBase = null;
    #[ORM\Column(name: 'precio_calculado', type: 'decimal', precision: 7, scale: 2, nullable: true)] private ?string $precioCalculado = null;
    #[ORM\Column(name: 'por_cobrar_sin_facturar', type: 'boolean', nullable: true)] private ?bool $porCobrarSinFacturar = null;
    #[ORM\Column(type: 'string', length: 50, unique: true)] private string $codigo;
    #[ORM\Column(name: 'codigo_externo_cliente', type: 'string', length: 50, nullable: true)] private ?string $codigoExternoCliente = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $observacion = null;
    #[ORM\Column(type: 'boolean', nullable: true)] private ?bool $transito = null;
    #[ORM\Column(name: 'fecha_creacion', type: 'datetime')] private \DateTime $fechaCreacion;
    #[ORM\ManyToOne(targetEntity: TipoEncomienda::class)] #[ORM\JoinColumn(name: 'tipo_encomienda_id', referencedColumnName: 'id')] private ?TipoEncomienda $tipoEncomienda = null;
    #[ORM\ManyToOne(targetEntity: Ruta::class)] #[ORM\JoinColumn(name: 'ruta_codigo', referencedColumnName: 'codigo')] private ?Ruta $ruta = null;
    #[ORM\ManyToOne(targetEntity: TipoEncomiendaEspeciales::class)] #[ORM\JoinColumn(name: 'tipo_encomienda_especial_id', referencedColumnName: 'id')] private ?TipoEncomiendaEspeciales $tipoEncomiendaEspecial = null;
    #[ORM\ManyToOne(targetEntity: Cliente::class)] #[ORM\JoinColumn(name: 'cliente_remitente', referencedColumnName: 'id')] private ?Cliente $clienteRemitente = null;
    #[ORM\ManyToOne(targetEntity: Cliente::class)] #[ORM\JoinColumn(name: 'cliente_destinatario', referencedColumnName: 'id')] private ?Cliente $clienteDestinatario = null;
    #[ORM\ManyToOne(targetEntity: Cliente::class)] #[ORM\JoinColumn(name: 'cliente_documento', referencedColumnName: 'id')] private ?Cliente $clienteDocumento = null;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_origen_id', referencedColumnName: 'id')] private ?Estacion $estacionOrigen = null;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_destino_id', referencedColumnName: 'id')] private ?Estacion $estacionDestino = null;
    #[ORM\ManyToOne(targetEntity: TipoDocumentoEncomienda::class)] #[ORM\JoinColumn(name: 'tipo_documento_id', referencedColumnName: 'id')] private ?TipoDocumentoEncomienda $tipoDocumento = null;
    #[ORM\ManyToOne(targetEntity: Moneda::class)] #[ORM\JoinColumn(name: 'moneda_id', referencedColumnName: 'id')] private ?Moneda $moneda = null;
    #[ORM\ManyToOne(targetEntity: TipoCambio::class)] #[ORM\JoinColumn(name: 'tipo_cambio_id', referencedColumnName: 'id')] private ?TipoCambio $tipoCambio = null;
    #[ORM\ManyToOne(targetEntity: TipoPago::class)] #[ORM\JoinColumn(name: 'tipo_pago_id', referencedColumnName: 'id')] private ?TipoPago $tipoPago = null;
    #[ORM\ManyToOne(targetEntity: FacturaGenerada::class)] #[ORM\JoinColumn(name: 'factura_generada_id', referencedColumnName: 'id')] private ?FacturaGenerada $facturaGenerada = null;
    #[ORM\ManyToOne(targetEntity: Empresa::class)] #[ORM\JoinColumn(name: 'empresa_id', referencedColumnName: 'id')] private ?Empresa $empresa = null;
    #[ORM\ManyToOne(targetEntity: Estacion::class)] #[ORM\JoinColumn(name: 'estacion_creacion_id', referencedColumnName: 'id')] private ?Estacion $estacionCreacion = null;
    #[ORM\ManyToOne(targetEntity: TarifaEncomienda::class)] #[ORM\JoinColumn(name: 'tarifa1_id', referencedColumnName: 'id')] private ?TarifaEncomienda $tarifa1 = null;
    #[ORM\ManyToOne(targetEntity: TarifaEncomienda::class)] #[ORM\JoinColumn(name: 'tarifa2_id', referencedColumnName: 'id')] private ?TarifaEncomienda $tarifa2 = null;
    #[ORM\ManyToOne(targetEntity: TarifaEncomienda::class)] #[ORM\JoinColumn(name: 'tarifa_distancia_id', referencedColumnName: 'id')] private ?TarifaEncomienda $tarifaDistancia = null;
    public function getId(): ?int { return $this->id; }
}
