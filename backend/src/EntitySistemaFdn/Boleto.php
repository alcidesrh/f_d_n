<?php

namespace App\EntitySistemaFdn;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'boleto')]
class Boleto
{
    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $fechaCreacion;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $fechaActualizacion = null;

    #[ORM\Column(name: 'precioCalculado', type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?string $precioCalculado = null;

    #[ORM\Column(name: 'precioCalculadoMonedaBase', type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?string $precioCalculadoMonedaBase = null;

    #[ORM\Column(name: 'tarifaAdicionalMonedaBase', type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?string $tarifaAdicionalMonedaBase = null;

    #[ORM\Column(name: 'revendidoEnEstacion', type: 'boolean')]
    private bool $revendidoEnEstacion = false;

    #[ORM\Column(name: 'revendidoEnCamino', type: 'boolean')]
    private bool $revendidoEnCamino = false;

    #[ORM\Column(name: 'utilizarDesdeEstacionOrigenSalida', type: 'boolean')]
    private bool $utilizarDesdeEstacionOrigenSalida = false;

    #[ORM\Column(name: 'observacionDestinoIntermedio', type: 'text', nullable: true)]
    private ?string $observacionDestinoIntermedio = null;

    #[ORM\Column(name: 'identificador_web', type: 'string', length: 50, nullable: true)]
    private ?string $identificadorWeb = null;

    #[ORM\Column(name: 'enviar_factura_email', type: 'integer')]
    private int $enviarFacturaEmail = 0;

    #[ORM\Column(name: 'ping_facturacion_especial', type: 'string', length: 255, nullable: true)]
    private ?string $pingFacturacionEspecial = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $observacion = null;

    #[ORM\ManyToOne(targetEntity: Salida::class)]
    #[ORM\JoinColumn(name: 'salida_id', referencedColumnName: 'id')]
    private ?Salida $salida = null;

    #[ORM\ManyToOne(targetEntity: AsientoBus::class)]
    #[ORM\JoinColumn(name: 'asiento_bus_id', referencedColumnName: 'id')]
    private ?AsientoBus $asientoBus = null;

    #[ORM\ManyToOne(targetEntity: Cliente::class)]
    #[ORM\JoinColumn(name: 'cliente_documento', referencedColumnName: 'id')]
    private ?Cliente $clienteDocumento = null;

    #[ORM\ManyToOne(targetEntity: Cliente::class)]
    #[ORM\JoinColumn(name: 'cliente_boleto', referencedColumnName: 'id')]
    private ?Cliente $clienteBoleto = null;

    #[ORM\ManyToOne(targetEntity: Estacion::class)]
    #[ORM\JoinColumn(name: 'estacion_origen_id', referencedColumnName: 'id')]
    private ?Estacion $estacionOrigen = null;

    #[ORM\ManyToOne(targetEntity: Estacion::class)]
    #[ORM\JoinColumn(name: 'estacion_destino_id', referencedColumnName: 'id')]
    private ?Estacion $estacionDestino = null;

    #[ORM\ManyToOne(targetEntity: EstadoBoleto::class)]
    #[ORM\JoinColumn(name: 'estado_id', referencedColumnName: 'id')]
    private ?EstadoBoleto $estado = null;

    #[ORM\ManyToOne(targetEntity: TipoPago::class)]
    #[ORM\JoinColumn(name: 'tipo_pago_id', referencedColumnName: 'id')]
    private ?TipoPago $tipoPago = null;

    #[ORM\Column(name: 'estacion_facturacion_especial', type: 'bigint', nullable: true)]
    private ?int $estacionFacturacionEspecial = null;

    #[ORM\Column(name: 'pagina_web_reserva_id', type: 'bigint', nullable: true)]
    private ?int $paginaWebReservaId = null;

    #[ORM\ManyToOne(targetEntity: VoucherAgencia::class)]
    #[ORM\JoinColumn(name: 'voucher_agencia_id', referencedColumnName: 'id')]
    private ?VoucherAgencia $voucherAgencia = null;

    #[ORM\ManyToOne(targetEntity: VoucherEstacion::class)]
    #[ORM\JoinColumn(name: 'voucher_estacion_id', referencedColumnName: 'id')]
    private ?VoucherEstacion $voucherEstacion = null;

    #[ORM\ManyToOne(targetEntity: VoucherInternet::class)]
    #[ORM\JoinColumn(name: 'voucher_internet_id', referencedColumnName: 'id')]
    private ?VoucherInternet $voucherInternet = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFechaCreacion(): \DateTime
    {
        return $this->fechaCreacion;
    }

    public function getFechaActualizacion(): ?\DateTime
    {
        return $this->fechaActualizacion;
    }

    public function getPrecioCalculado(): ?string
    {
        return $this->precioCalculado;
    }

    public function getSalida(): ?Salida
    {
        return $this->salida;
    }

    public function getAsientoBus(): ?AsientoBus
    {
        return $this->asientoBus;
    }

    public function getClienteDocumento(): ?Cliente
    {
        return $this->clienteDocumento;
    }

    public function getClienteBoleto(): ?Cliente
    {
        return $this->clienteBoleto;
    }

    public function getEstacionOrigen(): ?Estacion
    {
        return $this->estacionOrigen;
    }

    public function getEstacionDestino(): ?Estacion
    {
        return $this->estacionDestino;
    }

    public function getEstado(): ?EstadoBoleto
    {
        return $this->estado;
    }

    public function getTipoPago(): ?TipoPago
    {
        return $this->tipoPago;
    }

    public function getObservacion(): ?string
    {
        return $this->observacion;
    }
}
