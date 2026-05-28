<?php

namespace App\EntitySistemaFdn;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(readOnly: true)]
#[ORM\Table(name: 'estacion')]
class Estacion
{
    #[ORM\Id]
    #[ORM\Column(type: 'bigint')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(name: 'id_externo', type: 'bigint', nullable: true)]
    private ?int $idExternoBoleto = null;

    #[ORM\Column(name: 'id_externo_encomienda', type: 'bigint', nullable: true)]
    private ?int $idExternoEncomienda = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private string $nombre;

    #[ORM\Column(type: 'text')]
    private string $direccion;

    #[ORM\Column(type: 'string', length: 3, unique: true)]
    private string $alias;

    #[ORM\ManyToOne(targetEntity: TipoPagoEstacion::class)]
    #[ORM\JoinColumn(name: 'tipo_pago_id', referencedColumnName: 'id')]
    private ?TipoPagoEstacion $tipoPago = null;

    #[ORM\ManyToOne(targetEntity: Pais::class)]
    #[ORM\JoinColumn(name: 'pais_id', referencedColumnName: 'id', nullable: true)]
    private ?Pais $pais = null;

    #[ORM\Column(name: 'aplicar_porciento_tarifa_agencia', type: 'boolean', nullable: true)]
    private ?bool $aplicarPorcientoTarifaAgencia = null;

    #[ORM\Column(name: 'porciento_tarifa_agencia', type: 'decimal', precision: 10, scale: 8, nullable: true)]
    private ?string $porcientoTarifaAgencia = null;

    #[ORM\Column(name: 'inicia_ruta', type: 'boolean', nullable: true)]
    private ?bool $iniciaRuta = null;

    #[ORM\Column(name: 'destino', type: 'boolean', nullable: true)]
    private ?bool $destino = null;

    #[ORM\Column(name: 'enviosEncomiendasPorCobrar', type: 'boolean', nullable: true)]
    private ?bool $enviosEncomiendasPorCobrar = null;

    #[ORM\Column(name: 'permitirVoucherBoleto', type: 'boolean', nullable: true)]
    private ?bool $permitirVoucherBoleto = null;

    #[ORM\Column(name: 'permitirTarjeta', type: 'boolean', nullable: true)]
    private ?bool $permitirTarjeta = null;

    #[ORM\Column(name: 'pluginJavaActivo', type: 'boolean', nullable: true)]
    private ?bool $pluginJavaActivo = null;

    #[ORM\Column(type: 'boolean')]
    private bool $activo;

    #[ORM\Column(type: 'boolean')]
    private bool $publicidad;

    #[ORM\ManyToOne(targetEntity: TipoEstacion::class)]
    #[ORM\JoinColumn(name: 'tipoEstacion_id', referencedColumnName: 'id')]
    private ?TipoEstacion $tipo = null;

    #[ORM\ManyToOne(targetEntity: Moneda::class)]
    #[ORM\JoinColumn(name: 'agencia_moneda_id', referencedColumnName: 'id', nullable: true)]
    private ?Moneda $monedaAgencia = null;

    #[ORM\Column(name: 'agencia_porciento_bonificacion', type: 'decimal', precision: 10, scale: 8, nullable: true)]
    private ?string $porcientoBonificacion = null;

    #[ORM\Column(name: 'agencia_saldo', type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?string $saldo = null;

    #[ORM\Column(name: 'agencia_bonificacion', type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?string $bonificacion = null;

    #[ORM\Column(name: 'facturacion_especial', type: 'boolean', nullable: true)]
    private ?bool $facturacionEspecial = null;

    #[ORM\Column(name: 'ping_facturacion_especial', type: 'string', length: 8, nullable: true)]
    private ?string $pingFacturacionEspecial = null;

    #[ORM\OneToMany(targetEntity: TelefonoEstacion::class, mappedBy: 'estacion')]
    private Collection $listaTelefono;

    #[ORM\OneToMany(targetEntity: CorreoEstacion::class, mappedBy: 'estacion')]
    private Collection $listaCorreo;

    #[ORM\ManyToMany(targetEntity: ServicioEstacion::class)]
    #[ORM\JoinTable(name: 'estacion_servicio_union')]
    #[ORM\JoinColumn(name: 'estacion_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'servicio_id', referencedColumnName: 'id')]
    private Collection $listaServicio;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 10, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 10, nullable: true)]
    private ?string $latitude = null;

    #[ORM\ManyToOne(targetEntity: Departamento::class)]
    #[ORM\JoinColumn(name: 'departamento_id', referencedColumnName: 'id', nullable: true)]
    private ?Departamento $departamento = null;

    #[ORM\Column(name: 'control_tarjetas_en_ruta', type: 'boolean', nullable: true)]
    private ?bool $controlTarjetasEnRuta = null;

    #[ORM\Column(name: 'numEstablecimientoSat', type: 'bigint', nullable: true)]
    private ?int $numEstablecimientoSat = null;

    #[ORM\Column(name: 'numEstablecimientoSatMitocha', type: 'bigint', nullable: true)]
    private ?int $numEstablecimientoSatMitocha = null;

    #[ORM\Column(name: 'numEstablecimientoSatRosita', type: 'bigint', nullable: true)]
    private ?int $numEstablecimientoSatRosita = null;

    #[ORM\Column(name: 'numEstablecimientoSatMayaDeOro', type: 'bigint', nullable: true)]
    private ?int $numEstablecimientoSatMayaDeOro = null;

    #[ORM\Column(name: 'numEstablecimientoStarbus', type: 'bigint', nullable: true)]
    private ?int $numEstablecimientoStarbus = null;

    #[ORM\ManyToOne(targetEntity: FacturaEmisor::class)]
    #[ORM\JoinColumn(name: 'factura_emisor_id', referencedColumnName: 'id', nullable: true)]
    private ?FacturaEmisor $facturaEmisor = null;

    #[ORM\Column(type: 'decimal', precision: 7, scale: 2, nullable: true)]
    private ?string $precio = null;

    public function __construct()
    {
        $this->listaTelefono = new ArrayCollection();
        $this->listaCorreo = new ArrayCollection();
        $this->listaServicio = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function getDireccion(): string
    {
        return $this->direccion;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function getTipo(): ?TipoEstacion
    {
        return $this->tipo;
    }

    public function getPais(): ?Pais
    {
        return $this->pais;
    }

    public function getDepartamento(): ?Departamento
    {
        return $this->departamento;
    }

    public function getActivo(): bool
    {
        return $this->activo;
    }
}
