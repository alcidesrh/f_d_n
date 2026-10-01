<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use App\Entity\Base\TimeLegacyStatusBase;
use App\Entity\Embeddable\Precio;
use App\Entity\Enum\CanalVenta;
use App\Entity\Enum\EstadoBoletoVenta;
use App\Entity\Enum\EstadoFacturacion;
use App\Repository\BoletoVentaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * Venta de uno o más boletos de asiento (ADR-021). Solo se crea por
 * `App\Venta\RegistroVenta` (taquilla/agencia) o `App\Venta\CompraWeb`
 * (página): ahí se validan disponibilidad, tarifa, saldo y factura. Por eso
 * la API la expone solo para lectura.
 */
#[ORM\Entity(repositoryClass: BoletoVentaRepository::class)]
#[ORM\Index(columns: ["estado", "created_at"], name: "idx_boleto_venta_estado_creada")]
#[
    ApiResource(
        operations: [new Get(), new GetCollection()],
        graphQlOperations: [new Query(), new QueryCollection()],
    ),
]
class BoletoVenta extends TimeLegacyStatusBase
{
    /** Quien vendió (taquilla o agencia); null en las ventas de la página web. */
    #[ORM\ManyToOne]
    private ?Usuario $usuario = null;

    /**
     * @var Collection<int, BoletoAsiento>
     */
    #[
        ORM\OneToMany(
            targetEntity: BoletoAsiento::class,
            mappedBy: "boletoVenta",
            cascade: ["persist", "remove"],
            orphanRemoval: true,
        ),
    ]
    private Collection $asientos;

    #[ORM\OneToOne(cascade: ["persist", "remove"])]
    private ?Factura $factura = null;

    #[ORM\Column(length: 20, enumType: CanalVenta::class, options: ["default" => "estacion"])]
    private CanalVenta $canal = CanalVenta::ESTACION;

    #[ORM\Column(length: 20, enumType: EstadoBoletoVenta::class, options: ["default" => "confirmada"])]
    private EstadoBoletoVenta $estado = EstadoBoletoVenta::CONFIRMADA;

    #[ORM\Column(length: 20, enumType: EstadoFacturacion::class, options: ["default" => "no_aplica"])]
    private EstadoFacturacion $estadoFacturacion = EstadoFacturacion::NO_APLICA;

    /**
     * Número de acceso de contingencia (9 dígitos, SAT): se asigna al vender
     * sin factura electrónica y viaja en el DTE cuando se certifica después.
     */
    #[ORM\Column(nullable: true)]
    private ?int $numeroAcceso = null;

    /** Último error del certificador (contingencia o reintentos pendientes). */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $errorFacturacion = null;

    /** Estación donde se vendió (taquilla); null en agencia y web. */
    #[ORM\ManyToOne]
    private ?Estacion $estacion = null;

    #[ORM\ManyToOne]
    private ?Agencia $agencia = null;

    /** A quien se factura (el NIT/nombre del DTE). */
    #[ORM\ManyToOne]
    private ?Cliente $cliente = null;

    #[ORM\ManyToOne]
    private ?TipoPago $tipoPago = null;

    #[ORM\ManyToOne]
    private ?Moneda $moneda = null;

    #[ORM\Embedded(class: Precio::class)]
    private Precio $total;

    /** Asientos que no se cobran, emitidos en taquilla con permiso `venta.cortesia`: total 0 y sin factura. */
    #[ORM\Column(options: ["default" => false])]
    private bool $cortesia = false;

    /**
     * Boleto del legado emitido con voucher (`boleto.voucher_estacion_id`,
     * `voucher_agencia_id` o `voucher_internet_id`): no se cobró. Solo lo pone
     * la migración; el modelo nuevo emite cortesías.
     */
    #[ORM\Column(options: ["default" => false])]
    private bool $voucher = false;

    /** Enviar la factura al correo del cliente al confirmarse. */
    #[ORM\Column(options: ["default" => false])]
    private bool $enviarCorreo = false;

    /** Autorización de la pasarela de pago (web). */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $referenciaPago = null;

    /**
     * Identificador público de la venta (web): con él el cliente descarga su
     * boleto sin iniciar sesión. Es el token del carrito de reservas.
     */
    #[ApiProperty(readable: false, writable: false)]
    #[ORM\Column(type: "uuid", unique: true, nullable: true)]
    private ?Uuid $tokenPublico = null;

    public function __construct()
    {
        $this->asientos = new ArrayCollection();
        $this->total = new Precio();
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

    /**
     * @return Collection<int, BoletoAsiento>
     */
    public function getAsientos(): Collection
    {
        return $this->asientos;
    }

    public function addAsiento(BoletoAsiento $asiento): static
    {
        if (!$this->asientos->contains($asiento)) {
            $this->asientos->add($asiento);
            $asiento->setBoletoVenta($this);
        }

        return $this;
    }

    public function removeAsiento(BoletoAsiento $asiento): static
    {
        if ($this->asientos->removeElement($asiento)) {
            // set the owning side to null (unless already changed)
            if ($asiento->getBoletoVenta() === $this) {
                $asiento->setBoletoVenta(null);
            }
        }

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

    public function getCanal(): CanalVenta
    {
        return $this->canal;
    }

    public function setCanal(CanalVenta $canal): static
    {
        $this->canal = $canal;

        return $this;
    }

    public function getEstado(): EstadoBoletoVenta
    {
        return $this->estado;
    }

    public function setEstado(EstadoBoletoVenta $estado): static
    {
        $this->estado = $estado;

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
        $this->errorFacturacion = $errorFacturacion !== null
            ? mb_substr($errorFacturacion, 0, 500)
            : null;

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

    public function getEstacion(): ?Estacion
    {
        return $this->estacion;
    }

    public function setEstacion(?Estacion $estacion): static
    {
        $this->estacion = $estacion;

        return $this;
    }

    public function getAgencia(): ?Agencia
    {
        return $this->agencia;
    }

    public function setAgencia(?Agencia $agencia): static
    {
        $this->agencia = $agencia;

        return $this;
    }

    public function getCliente(): ?Cliente
    {
        return $this->cliente;
    }

    public function setCliente(?Cliente $cliente): static
    {
        $this->cliente = $cliente;

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

    public function getTotal(): Money
    {
        return $this->total->toMoney();
    }

    public function setTotal(Money $total): static
    {
        $this->total = Precio::fromMoney($total);

        return $this;
    }

    public function isCortesia(): bool
    {
        return $this->cortesia;
    }

    public function setCortesia(bool $cortesia): static
    {
        $this->cortesia = $cortesia;

        return $this;
    }

    public function isVoucher(): bool
    {
        return $this->voucher;
    }

    public function setVoucher(bool $voucher): static
    {
        $this->voucher = $voucher;

        return $this;
    }

    public function isEnviarCorreo(): bool
    {
        return $this->enviarCorreo;
    }

    public function setEnviarCorreo(bool $enviarCorreo): static
    {
        $this->enviarCorreo = $enviarCorreo;

        return $this;
    }

    public function getReferenciaPago(): ?string
    {
        return $this->referenciaPago;
    }

    public function setReferenciaPago(?string $referenciaPago): static
    {
        $this->referenciaPago = $referenciaPago;

        return $this;
    }

    /** Fecha de la venta (null en ventas migradas sin fecha). */
    public function getCreada(): ?\DateTime
    {
        return isset($this->createdAt) ? $this->createdAt : null;
    }

    /**
     * `tokenPublico` de la venta del regreso de un carrito web de ida y
     * vuelta: derivado del token del carrito (el de la ida), para que siga
     * siendo único.
     */
    public static function tokenRegreso(Uuid $carrito): Uuid
    {
        return Uuid::v5($carrito, "regreso");
    }

    public function getTokenPublico(): ?Uuid
    {
        return $this->tokenPublico;
    }

    public function setTokenPublico(?Uuid $tokenPublico): static
    {
        $this->tokenPublico = $tokenPublico;

        return $this;
    }
}
