<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Embeddable\Precio;
use App\Entity\Enum\EstadoEncomienda;
use App\Entity\Enum\EstadoFacturacion;
use App\Entity\Enum\ModalidadEncomienda;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * Un envío de paquetería en la bodega de los buses (ADR-035): de un
 * remitente a un destinatario, de un enclave a otro, con uno o más artículos.
 * Viaja y se entrega entera; cada bulto lleva su guía con el mismo código.
 *
 * Solo se crea y cambia por `App\Encomienda` (registro, venta con boleto,
 * embarque, entrega, anulación): no se expone a la API genérica. La bitácora
 * la escribe `App\Bitacora\RegistradorBitacora`.
 */
#[ORM\Entity]
#[ORM\Index(columns: ["estado", "ubicacion_id"], name: "idx_encomienda_estado_ubicacion")]
#[ORM\Index(columns: ["salida_id"], name: "idx_encomienda_salida")]
#[ORM\Index(columns: ["creada"], name: "idx_encomienda_creada")]
#[ORM\Index(columns: ["estado_facturacion"], name: "idx_encomienda_facturacion")]
class Encomienda
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Clave de idempotencia del registro (la genera el cliente). */
    #[ORM\Column(type: "uuid", unique: true, nullable: true)]
    private ?Uuid $token = null;

    /** La que la transporta y la factura; solo se embarca en sus salidas. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Empresa $empresa = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Enclave $origen = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Enclave $destino = null;

    /** Ruta prevista al registrarla (informativa: puede hacer transbordo). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Ruta $ruta = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Cliente $remitente = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Cliente $destinatario = null;

    #[ORM\Column(length: 20, enumType: ModalidadEncomienda::class)]
    private ModalidadEncomienda $modalidad = ModalidadEncomienda::PAGADA;

    #[ORM\Column(length: 20, enumType: EstadoEncomienda::class)]
    private EstadoEncomienda $estado = EstadoEncomienda::RECIBIDA;

    /** Enclave en cuya bodega está (recibida o desembarcada); null a bordo, entregada o anulada. */
    #[ORM\ManyToOne]
    private ?Enclave $ubicacion = null;

    /** Salida en la que va (embarcada o en tránsito) o la última en que fue. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Salida $salida = null;

    /** Venta del boleto que la cobra (modalidad `con_boleto`). */
    #[ORM\ManyToOne(inversedBy: "encomiendas")]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    private ?BoletoVenta $boletoVenta = null;

    /** Boleto del pasajero que la lleva (modalidad `con_boleto`). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?BoletoAsiento $boleto = null;

    /**
     * @var Collection<int, EncomiendaArticulo>
     */
    #[ORM\OneToMany(targetEntity: EncomiendaArticulo::class, mappedBy: "encomienda", cascade: ["persist", "remove"], orphanRemoval: true)]
    #[ORM\OrderBy(["id" => "ASC"])]
    private Collection $articulos;

    /** Lo que se cobra (ya con el descuento por bultos). */
    #[ORM\Embedded(class: Precio::class, columnPrefix: "total_")]
    private Precio $total;

    /** Descuento por cantidad de bultos incluido en el total. */
    #[ORM\Embedded(class: Precio::class, columnPrefix: "descuento_")]
    private Precio $descuento;

    /** A quién se factura: el remitente si es pagada; quien la paga al retirarla si es por cobrar. */
    #[ORM\ManyToOne]
    private ?Cliente $clienteFactura = null;

    #[ORM\OneToOne(cascade: ["persist"])]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Factura $factura = null;

    #[ORM\Column(length: 20, enumType: EstadoFacturacion::class, options: ["default" => "no_aplica"])]
    private EstadoFacturacion $estadoFacturacion = EstadoFacturacion::NO_APLICA;

    /** Número de acceso de contingencia (SAT) si se cobró sin factura electrónica. */
    #[ORM\Column(nullable: true)]
    private ?int $numeroAcceso = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $errorFacturacion = null;

    #[ORM\ManyToOne]
    private ?TipoPago $tipoPago = null;

    #[ORM\ManyToOne]
    private ?Moneda $moneda = null;

    /** Quien la registró. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Usuario $usuario = null;

    /** Estación donde se registró (la del usuario). */
    #[ORM\ManyToOne]
    private ?Estacion $estacion = null;

    /** Quien la retiró (el destinatario u otro en su nombre). */
    #[ORM\ManyToOne]
    private ?Cliente $receptor = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $entregadaEn = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $observacion = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motivoAnulacion = null;

    #[ORM\Column]
    private \DateTimeImmutable $creada;

    /** Id en el legado (`encomienda.id`), si se migra. */
    #[ORM\Column(length: 20, nullable: true, unique: true)]
    private ?string $legacyId = null;

    public function __construct()
    {
        $this->articulos = new ArrayCollection();
        $this->total = new Precio();
        $this->descuento = new Precio();
        $this->creada = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /** Lo que va en el código de barras (8 dígitos, como los boletos). */
    public function getCodigo(): string
    {
        return sprintf("%08d", $this->id);
    }

    public function getToken(): ?Uuid
    {
        return $this->token;
    }

    public function setToken(?Uuid $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getEmpresa(): ?Empresa
    {
        return $this->empresa;
    }

    public function setEmpresa(Empresa $empresa): static
    {
        $this->empresa = $empresa;

        return $this;
    }

    public function getOrigen(): ?Enclave
    {
        return $this->origen;
    }

    public function setOrigen(Enclave $origen): static
    {
        $this->origen = $origen;

        return $this;
    }

    public function getDestino(): ?Enclave
    {
        return $this->destino;
    }

    public function setDestino(Enclave $destino): static
    {
        $this->destino = $destino;

        return $this;
    }

    public function getRuta(): ?Ruta
    {
        return $this->ruta;
    }

    public function setRuta(?Ruta $ruta): static
    {
        $this->ruta = $ruta;

        return $this;
    }

    public function getRemitente(): ?Cliente
    {
        return $this->remitente;
    }

    public function setRemitente(Cliente $remitente): static
    {
        $this->remitente = $remitente;

        return $this;
    }

    public function getDestinatario(): ?Cliente
    {
        return $this->destinatario;
    }

    public function setDestinatario(Cliente $destinatario): static
    {
        $this->destinatario = $destinatario;

        return $this;
    }

    public function getModalidad(): ModalidadEncomienda
    {
        return $this->modalidad;
    }

    public function setModalidad(ModalidadEncomienda $modalidad): static
    {
        $this->modalidad = $modalidad;

        return $this;
    }

    public function getEstado(): EstadoEncomienda
    {
        return $this->estado;
    }

    /**
     * Lo usa el workflow `encomienda` (ADR-038): fuera de la creación, el
     * estado se cambia con `App\Encomienda\FlujoEncomienda`, nunca aquí
     * directo. Valida igual que la máquina (la bitácora la anota el listener).
     *
     * @throws \DomainException si la transición no está permitida
     */
    public function setEstado(EstadoEncomienda $estado): static
    {
        if ($estado !== $this->estado && isset($this->id) && !$this->estado->puedeTransicionarA($estado)) {
            throw new \DomainException(sprintf("Una encomienda %s no puede pasar a %s.", mb_strtolower($this->estado->etiqueta()), mb_strtolower($estado->etiqueta())));
        }
        $this->estado = $estado;

        return $this;
    }

    public function getUbicacion(): ?Enclave
    {
        return $this->ubicacion;
    }

    public function setUbicacion(?Enclave $ubicacion): static
    {
        $this->ubicacion = $ubicacion;

        return $this;
    }

    public function getSalida(): ?Salida
    {
        return $this->salida;
    }

    public function setSalida(?Salida $salida): static
    {
        $this->salida = $salida;

        return $this;
    }

    public function getBoletoVenta(): ?BoletoVenta
    {
        return $this->boletoVenta;
    }

    public function setBoletoVenta(?BoletoVenta $boletoVenta): static
    {
        $this->boletoVenta = $boletoVenta;

        return $this;
    }

    public function getBoleto(): ?BoletoAsiento
    {
        return $this->boleto;
    }

    public function setBoleto(?BoletoAsiento $boleto): static
    {
        $this->boleto = $boleto;

        return $this;
    }

    /**
     * @return Collection<int, EncomiendaArticulo>
     */
    public function getArticulos(): Collection
    {
        return $this->articulos;
    }

    public function addArticulo(EncomiendaArticulo $articulo): static
    {
        if (!$this->articulos->contains($articulo)) {
            $this->articulos->add($articulo);
            $articulo->setEncomienda($this);
        }

        return $this;
    }

    /** Cuántos bultos lleva (una guía por bulto). */
    public function getBultos(): int
    {
        return array_sum(array_map(static fn(EncomiendaArticulo $a) => $a->getCantidad(), $this->articulos->toArray()));
    }

    public function getTotal(): Money
    {
        return $this->total->toMoney();
    }

    public function setTotal(Money $total): static
    {
        $this->total = Precio::fromMoney($total);

        return $this;
    }

    public function getDescuento(): Money
    {
        return $this->descuento->toMoney();
    }

    public function setDescuento(Money $descuento): static
    {
        $this->descuento = Precio::fromMoney($descuento);

        return $this;
    }

    public function getClienteFactura(): ?Cliente
    {
        return $this->clienteFactura;
    }

    public function setClienteFactura(?Cliente $clienteFactura): static
    {
        $this->clienteFactura = $clienteFactura;

        return $this;
    }

    public function getFactura(): ?Factura
    {
        return $this->factura;
    }

    public function setFactura(?Factura $factura): static
    {
        $this->factura = $factura;

        return $this;
    }

    /** Registra el DTE certificado. */
    public function certificar(Factura $factura): static
    {
        $this->factura = $factura;
        $this->estadoFacturacion = EstadoFacturacion::CERTIFICADA;
        $this->errorFacturacion = null;

        return $this;
    }

    public function getEstadoFacturacion(): EstadoFacturacion
    {
        return $this->estadoFacturacion;
    }

    public function setEstadoFacturacion(EstadoFacturacion $estadoFacturacion): static
    {
        $this->estadoFacturacion = $estadoFacturacion;

        return $this;
    }

    public function getNumeroAcceso(): ?int
    {
        return $this->numeroAcceso;
    }

    public function setNumeroAcceso(?int $numeroAcceso): static
    {
        $this->numeroAcceso = $numeroAcceso;

        return $this;
    }

    public function getErrorFacturacion(): ?string
    {
        return $this->errorFacturacion;
    }

    public function setErrorFacturacion(?string $errorFacturacion): static
    {
        $this->errorFacturacion = $errorFacturacion !== null ? mb_substr($errorFacturacion, 0, 500) : null;

        return $this;
    }

    public function getTipoPago(): ?TipoPago
    {
        return $this->tipoPago;
    }

    public function setTipoPago(?TipoPago $tipoPago): static
    {
        $this->tipoPago = $tipoPago;

        return $this;
    }

    public function getMoneda(): ?Moneda
    {
        return $this->moneda;
    }

    public function setMoneda(?Moneda $moneda): static
    {
        $this->moneda = $moneda;

        return $this;
    }

    public function getUsuario(): ?Usuario
    {
        return $this->usuario;
    }

    public function setUsuario(?Usuario $usuario): static
    {
        $this->usuario = $usuario;

        return $this;
    }

    public function getEstacion(): ?Estacion
    {
        return $this->estacion;
    }

    public function setEstacion(?Estacion $estacion): static
    {
        $this->estacion = $estacion;

        return $this;
    }

    public function getReceptor(): ?Cliente
    {
        return $this->receptor;
    }

    public function setReceptor(?Cliente $receptor): static
    {
        $this->receptor = $receptor;

        return $this;
    }

    public function getEntregadaEn(): ?\DateTimeImmutable
    {
        return $this->entregadaEn;
    }

    public function setEntregadaEn(?\DateTimeImmutable $entregadaEn): static
    {
        $this->entregadaEn = $entregadaEn;

        return $this;
    }

    public function getObservacion(): ?string
    {
        return $this->observacion;
    }

    public function setObservacion(?string $observacion): static
    {
        $this->observacion = $observacion !== null ? mb_substr($observacion, 0, 255) : null;

        return $this;
    }

    public function getMotivoAnulacion(): ?string
    {
        return $this->motivoAnulacion;
    }

    public function setMotivoAnulacion(?string $motivoAnulacion): static
    {
        $this->motivoAnulacion = $motivoAnulacion !== null ? mb_substr($motivoAnulacion, 0, 255) : null;

        return $this;
    }

    public function getCreada(): \DateTimeImmutable
    {
        return $this->creada;
    }

    public function setCreada(\DateTimeImmutable $creada): static
    {
        $this->creada = $creada;

        return $this;
    }

    public function getLegacyId(): ?string
    {
        return $this->legacyId;
    }

    public function setLegacyId(?string $legacyId): static
    {
        $this->legacyId = $legacyId;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf("Encomienda %s", $this->getCodigo());
    }
}
