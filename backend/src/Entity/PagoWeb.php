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
 * necesario para seguir el cobro entre los pasos de 3-D Secure (datos del
 * comprador, referencia y estado de la pasarela) y deja rastro de cada
 * intento. Nunca guarda datos de la tarjeta, salvo marca y últimos cuatro
 * dígitos: en cada paso la página los vuelve a enviar.
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

    /** Empresa que cobra (su comercio en la pasarela). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Empresa $empresa = null;

    /**
     * Lo que la pasarela necesita para el siguiente paso del cobro (3-D
     * Secure); nunca datos de la tarjeta.
     *
     * @var array<string, string>|null
     */
    #[ORM\Column(type: "json", nullable: true)]
    private ?array $estadoPasarela = null;

    /** Venta de la ida (o la única). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?BoletoVenta $boletoVenta = null;

    /** Venta del regreso en ida y vuelta (su `tokenPublico` es `BoletoVenta::tokenRegreso($token)`). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?BoletoVenta $boletoVentaRegreso = null;

    /** Recargo de la página al empezar el pago: el que se cobra aunque cambie mientras tanto. */
    #[ORM\Column(type: "decimal", precision: 5, scale: 2, options: ["default" => "0.00"])]
    private string $recargoPorciento = "0.00";

    /** Viajes del carrito: 1, o 2 si es ida y vuelta. */
    #[ORM\Column(type: "smallint", options: ["default" => 1])]
    private int $viajes = 1;

    #[ORM\Column]
    private \DateTimeImmutable $creado;

    #[ORM\Column]
    private \DateTimeImmutable $actualizado;

    /**
     * @param array<string, mixed> $comprador
     */
    public function __construct(Uuid $token, Money $monto, array $comprador, string $marca, string $ultimos4, ?Empresa $empresa = null, string $recargoPorciento = "0.00", int $viajes = 1)
    {
        $this->recargoPorciento = $recargoPorciento;
        $this->viajes = $viajes;
        $this->token = $token;
        $this->empresa = $empresa;
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
        if ($estado !== EstadoPagoWeb::AUTENTICACION) {
            $this->estadoPasarela = null;
        }
        $this->estado = $estado;
        $this->referenciaPasarela = $referenciaPasarela ?? $this->referenciaPasarela;
        $this->autorizacion = $autorizacion ?? $this->autorizacion;
        $this->mensaje = $mensaje !== null ? mb_substr($mensaje, 0, 500) : $this->mensaje;
        $this->actualizado = new \DateTimeImmutable();
    }

    /**
     * Espera un paso del navegador (datos del dispositivo o desafío 3-D Secure).
     *
     * @param array<string, string> $estadoPasarela
     */
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

    public function getEmpresa(): ?Empresa
    {
        return $this->empresa;
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

    public function completar(BoletoVenta $venta, ?BoletoVenta $regreso = null): void
    {
        $this->boletoVenta = $venta;
        $this->boletoVentaRegreso = $regreso;
        $this->registrar(EstadoPagoWeb::COMPLETADO);
    }

    public function getCreado(): \DateTimeImmutable
    {
        return $this->creado;
    }

    public function getActualizado(): \DateTimeImmutable
    {
        return $this->actualizado;
    }

    public function getBoletoVentaRegreso(): ?BoletoVenta
    {
        return $this->boletoVentaRegreso;
    }

    public function getRecargoPorciento(): string
    {
        return $this->recargoPorciento;
    }

    public function getViajes(): int
    {
        return $this->viajes;
    }
}
