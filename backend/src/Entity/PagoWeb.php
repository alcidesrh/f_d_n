<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Embeddable\Precio;
use App\Entity\Enum\EstadoPagoWeb;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * Intento de cobro de un carrito de la página web (ADR-021). Guarda lo
 * necesario para terminar la compra después de 3-D Secure (datos del
 * comprador, referencia de la pasarela) y deja rastro de cada intento. Nunca
 * guarda datos de la tarjeta, salvo marca y últimos cuatro dígitos.
 */
#[ORM\Entity]
#[ORM\Index(columns: ["token"], name: "idx_pago_web_token")]
#[ORM\UniqueConstraint(name: "uq_pago_web_referencia", columns: ["referencia_pasarela"])]
class PagoWeb
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Token del carrito (`ReservaAsiento.token`). */
    #[ORM\Column(type: "uuid")]
    private Uuid $token;

    #[ORM\Column(length: 20, enumType: EstadoPagoWeb::class)]
    private EstadoPagoWeb $estado;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $referenciaPasarela = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $autorizacion = null;

    #[ORM\Embedded(class: Precio::class)]
    private Precio $monto;

    /**
     * Datos del comprador para registrar el cliente y facturar.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: "json")]
    private array $comprador;

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $marca = null;

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $ultimos4 = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $mensaje = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?BoletoVenta $boletoVenta = null;

    #[ORM\Column]
    private \DateTimeImmutable $creado;

    #[ORM\Column]
    private \DateTimeImmutable $actualizado;

    /**
     * @param array<string, mixed> $comprador
     */
    public function __construct(Uuid $token, Money $monto, array $comprador, string $marca, string $ultimos4)
    {
        $this->token = $token;
        $this->monto = Precio::fromMoney($monto);
        $this->comprador = $comprador;
        $this->marca = $marca;
        $this->ultimos4 = $ultimos4;
        $this->estado = EstadoPagoWeb::AUTENTICACION;
        $this->creado = $this->actualizado = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): Uuid
    {
        return $this->token;
    }

    public function getEstado(): EstadoPagoWeb
    {
        return $this->estado;
    }

    public function registrar(EstadoPagoWeb $estado, ?string $referenciaPasarela = null, ?string $autorizacion = null, ?string $mensaje = null): void
    {
        $this->estado = $estado;
        $this->referenciaPasarela = $referenciaPasarela ?? $this->referenciaPasarela;
        $this->autorizacion = $autorizacion ?? $this->autorizacion;
        $this->mensaje = $mensaje !== null ? mb_substr($mensaje, 0, 500) : $this->mensaje;
        $this->actualizado = new \DateTimeImmutable();
    }

    public function getReferenciaPasarela(): ?string
    {
        return $this->referenciaPasarela;
    }

    public function getAutorizacion(): ?string
    {
        return $this->autorizacion;
    }

    public function getMonto(): Money
    {
        return $this->monto->toMoney();
    }

    /** @return array<string, mixed> */
    public function getComprador(): array
    {
        return $this->comprador;
    }

    public function getMarca(): ?string
    {
        return $this->marca;
    }

    public function getUltimos4(): ?string
    {
        return $this->ultimos4;
    }

    public function getMensaje(): ?string
    {
        return $this->mensaje;
    }

    public function getBoletoVenta(): ?BoletoVenta
    {
        return $this->boletoVenta;
    }

    public function completar(BoletoVenta $venta): void
    {
        $this->boletoVenta = $venta;
        $this->registrar(EstadoPagoWeb::COMPLETADO);
    }

    public function getCreado(): \DateTimeImmutable
    {
        return $this->creado;
    }
}
