<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Embeddable\Precio;
use App\Entity\Enum\EstadoPagoWeb;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * Modificación de un viaje comprado en la página, hecha por el cliente desde
 * «Mis compras» (cambiar hora, ruta, asiento o clase y agregar asientos).
 * Deja rastro de cada intento y, si hay que cobrar (diferencia de precio,
 * asientos nuevos, penalización), guarda lo necesario para seguir el cobro
 * entre los pasos de 3-D Secure, como `PagoWeb`. Sin cobro se aplica al
 * instante y queda `completado`.
 *
 * `token` identifica los asientos apartados mientras paga (`ReservaAsiento`)
 * y es la referencia del cobro en la pasarela. Nunca guarda datos de la
 * tarjeta, salvo marca y últimos cuatro dígitos.
 */
#[ORM\Entity]
#[ORM\Index(columns: ["boleto_venta_id"], name: "idx_modificacion_compra_venta")]
#[ORM\UniqueConstraint(name: "uq_modificacion_compra_token", columns: ["token"])]
class ModificacionCompra
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: "uuid")]
    private Uuid $token;

    /** El viaje que se modifica. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private BoletoVenta $boletoVenta;

    #[ORM\Column(length: 20, enumType: EstadoPagoWeb::class)]
    private EstadoPagoWeb $estado = EstadoPagoWeb::AUTENTICACION;

    /**
     * Lo que pidió el cliente (`SolicitudModificacion::toArray()`): se vuelve
     * a aplicar igual tras cobrar.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: "json")]
    private array $cambios;

    /** Lo que se cobra: diferencias + asientos nuevos + penalización. */
    #[ORM\Embedded(class: Precio::class)]
    private Precio $monto;

    #[ORM\Column(options: ["default" => 0])]
    private int $penalizacion = 0;

    /** Correo con el que entró el cliente (su llave). */
    #[ORM\Column(length: 180)]
    private string $correo;

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $marca = null;

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $ultimos4 = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $referenciaPasarela = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $autorizacion = null;

    /** @var array<string, string>|null */
    #[ORM\Column(type: "json", nullable: true)]
    private ?array $estadoPasarela = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $mensaje = null;

    /** Si se anuló la factura y se emitió otra (punto 4 de la regla del negocio). */
    #[ORM\Column(options: ["default" => false])]
    private bool $refacturada = false;

    #[ORM\Column]
    private \DateTimeImmutable $creada;

    #[ORM\Column]
    private \DateTimeImmutable $actualizada;

    /**
     * @param array<string, mixed> $cambios
     */
    public function __construct(Uuid $token, BoletoVenta $venta, array $cambios, Money $monto, Money $penalizacion, string $correo)
    {
        $this->token = $token;
        $this->boletoVenta = $venta;
        $this->cambios = $cambios;
        $this->monto = Precio::fromMoney($monto);
        $this->penalizacion = (int) $penalizacion->getAmount();
        $this->correo = $correo;
        $this->creada = $this->actualizada = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): Uuid
    {
        return $this->token;
    }

    public function getBoletoVenta(): BoletoVenta
    {
        return $this->boletoVenta;
    }

    public function getEstado(): EstadoPagoWeb
    {
        return $this->estado;
    }

    /** @return array<string, mixed> */
    public function getCambios(): array
    {
        return $this->cambios;
    }

    public function getMonto(): Money
    {
        return $this->monto->toMoney();
    }

    public function getPenalizacion(): Money
    {
        return new Money($this->penalizacion, $this->getMonto()->getCurrency());
    }

    public function getCorreo(): string
    {
        return $this->correo;
    }

    public function conTarjeta(string $marca, string $ultimos4): void
    {
        $this->marca = $marca;
        $this->ultimos4 = $ultimos4;
    }

    public function getMarca(): ?string
    {
        return $this->marca;
    }

    public function getUltimos4(): ?string
    {
        return $this->ultimos4;
    }

    public function getReferenciaPasarela(): ?string
    {
        return $this->referenciaPasarela;
    }

    public function getAutorizacion(): ?string
    {
        return $this->autorizacion;
    }

    public function getMensaje(): ?string
    {
        return $this->mensaje;
    }

    public function registrar(EstadoPagoWeb $estado, ?string $referenciaPasarela = null, ?string $autorizacion = null, ?string $mensaje = null): void
    {
        if ($estado !== EstadoPagoWeb::AUTENTICACION) {
            $this->estadoPasarela = null;
        }
        $this->estado = $estado;
        $this->referenciaPasarela = $referenciaPasarela ?? $this->referenciaPasarela;
        $this->autorizacion = $autorizacion ?? $this->autorizacion;
        $this->mensaje = $mensaje !== null ? mb_substr($mensaje, 0, 500) : $this->mensaje;
        $this->actualizada = new \DateTimeImmutable();
    }

    /** @param array<string, string> $estadoPasarela */
    public function esperarNavegador(?string $referenciaPasarela, array $estadoPasarela): void
    {
        $this->registrar(EstadoPagoWeb::AUTENTICACION, $referenciaPasarela !== "" ? $referenciaPasarela : null);
        $this->estadoPasarela = $estadoPasarela;
    }

    /** @return array<string, string> */
    public function getEstadoPasarela(): array
    {
        return $this->estadoPasarela ?? [];
    }

    public function marcarRefacturada(): void
    {
        $this->refacturada = true;
        $this->actualizada = new \DateTimeImmutable();
    }

    public function isRefacturada(): bool
    {
        return $this->refacturada;
    }

    public function getCreada(): \DateTimeImmutable
    {
        return $this->creada;
    }

    public function getActualizada(): \DateTimeImmutable
    {
        return $this->actualizada;
    }
}
