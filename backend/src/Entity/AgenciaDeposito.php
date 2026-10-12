<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\EstadoDepositoAgencia;
use App\Venta\Excepcion\VentaRechazada;
use Doctrine\ORM\Mapping as ORM;

/**
 * Boleta de depósito bancario de una agencia (ADR-034, el "Depósito de
 * Agencia" del legado). Se registra sin tocar el saldo; al acreditarla,
 * `App\Venta\Agencia\DepositosAgencia` mueve el saldo por `SaldoAgencia`
 * (depósito + bonificación si `aplicaBono`); al rechazarla queda el motivo.
 * Solo la API REST `/api/depositos-agencia` la expone.
 */
#[ORM\Entity]
#[ORM\Index(columns: ["agencia_id", "fecha"], name: "idx_agencia_deposito_agencia_fecha")]
#[ORM\Index(columns: ["fecha"], name: "idx_agencia_deposito_fecha")]
#[ORM\Index(columns: ["numero_boleta"], name: "idx_agencia_deposito_boleta")]
class AgenciaDeposito
{
    public const MAX_BOLETA = 30;
    public const MAX_OBSERVACION = 200;
    public const MAX_MOTIVO = 200;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Agencia $agencia;

    /** Día del depósito en el banco (no el del registro). */
    #[ORM\Column(type: "date_immutable")]
    private \DateTimeImmutable $fecha;

    /** Centavos de `moneda`, siempre > 0. */
    #[ORM\Column(type: "bigint")]
    private int|string $importe;

    #[ORM\Column(length: 3)]
    private string $moneda;

    #[ORM\Column(length: self::MAX_BOLETA)]
    private string $numeroBoleta;

    /** Si al acreditarlo se suma la bonificación de la agencia. */
    #[ORM\Column]
    private bool $aplicaBono;

    /** Bonificación acreditada (centavos); 0 mientras no se acredite. */
    #[ORM\Column(type: "bigint", options: ["default" => 0])]
    private int|string $bono = 0;

    #[ORM\Column(length: self::MAX_OBSERVACION, nullable: true)]
    private ?string $observacion;

    #[ORM\Column(length: 20, enumType: EstadoDepositoAgencia::class)]
    private EstadoDepositoAgencia $estado = EstadoDepositoAgencia::REGISTRADO;

    #[ORM\Column(length: self::MAX_MOTIVO, nullable: true)]
    private ?string $motivoRechazo = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Usuario $creadoPor;

    #[ORM\Column]
    private \DateTimeImmutable $creadoEn;

    /** Quién lo acreditó o rechazó, y cuándo. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Usuario $resueltoPor = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resueltoEn = null;

    #[ORM\Column(length: 50, unique: true, nullable: true)]
    private ?string $legacyId = null;

    public function __construct(
        Agencia $agencia,
        \DateTimeImmutable $fecha,
        int $importe,
        string $numeroBoleta,
        bool $aplicaBono,
        ?string $observacion,
        ?Usuario $creadoPor,
        \DateTimeImmutable $creadoEn,
    ) {
        if ($importe <= 0) {
            throw new VentaRechazada("El importe del depósito debe ser mayor que cero.", "importe_invalido");
        }
        $this->agencia = $agencia;
        $this->fecha = $fecha->setTime(0, 0);
        $this->importe = $importe;
        $this->moneda = $agencia->getMoneda();
        $this->numeroBoleta = $numeroBoleta;
        $this->aplicaBono = $aplicaBono;
        $this->observacion = $observacion;
        $this->creadoPor = $creadoPor;
        $this->creadoEn = $creadoEn;
    }

    /** Solo `DepositosAgencia`, después de mover el saldo. */
    public function acreditar(int $bono, ?Usuario $usuario, \DateTimeImmutable $cuando): void
    {
        $this->exigirRegistrado("acreditar");
        $this->estado = EstadoDepositoAgencia::ACREDITADO;
        $this->bono = $bono;
        $this->resueltoPor = $usuario;
        $this->resueltoEn = $cuando;
    }

    public function rechazar(string $motivo, ?Usuario $usuario, \DateTimeImmutable $cuando): void
    {
        $this->exigirRegistrado("rechazar");
        $motivo = trim($motivo);
        if ($motivo === "") {
            throw new VentaRechazada("Indique el motivo del rechazo.", "motivo_requerido");
        }
        $this->estado = EstadoDepositoAgencia::RECHAZADO;
        $this->motivoRechazo = mb_substr($motivo, 0, self::MAX_MOTIVO);
        $this->resueltoPor = $usuario;
        $this->resueltoEn = $cuando;
    }

    /** @throws VentaRechazada si ya se acreditó o rechazó */
    public function exigirRegistrado(string $accion): void
    {
        if ($this->estado !== EstadoDepositoAgencia::REGISTRADO) {
            throw new VentaRechazada(
                sprintf("Solo se puede %s un depósito registrado; este está %s.", $accion, $this->estado->value),
                "deposito_resuelto",
                409,
            );
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAgencia(): Agencia
    {
        return $this->agencia;
    }

    public function getFecha(): \DateTimeImmutable
    {
        return $this->fecha;
    }

    public function getImporte(): int
    {
        return (int) $this->importe;
    }

    public function getMoneda(): string
    {
        return $this->moneda;
    }

    public function getNumeroBoleta(): string
    {
        return $this->numeroBoleta;
    }

    public function isAplicaBono(): bool
    {
        return $this->aplicaBono;
    }

    public function getBono(): int
    {
        return (int) $this->bono;
    }

    public function getObservacion(): ?string
    {
        return $this->observacion;
    }

    public function getEstado(): EstadoDepositoAgencia
    {
        return $this->estado;
    }

    public function getMotivoRechazo(): ?string
    {
        return $this->motivoRechazo;
    }

    public function getCreadoPor(): ?Usuario
    {
        return $this->creadoPor;
    }

    public function getCreadoEn(): \DateTimeImmutable
    {
        return $this->creadoEn;
    }

    public function getResueltoPor(): ?Usuario
    {
        return $this->resueltoPor;
    }

    public function getResueltoEn(): ?\DateTimeImmutable
    {
        return $this->resueltoEn;
    }

    public function getLegacyId(): ?string
    {
        return $this->legacyId;
    }

    public function getLabel(): string
    {
        return sprintf("Depósito %s · %s", $this->numeroBoleta, $this->agencia->getNombre());
    }
}
