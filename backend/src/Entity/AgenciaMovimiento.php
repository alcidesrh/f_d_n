<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use App\Filter\ColumnaFilter;
use App\Entity\Enum\TipoMovimientoAgencia;
use Doctrine\ORM\Mapping as ORM;

/**
 * Asiento del libro de saldo de una agencia: inmutable, solo lectura por la
 * API. Lo crea `App\Venta\Agencia\SaldoAgencia` en la misma transacción que
 * cambia `Agencia.saldo`, así el saldo siempre es la suma de los movimientos.
 */
#[ORM\Entity]
#[ORM\Index(columns: ["agencia_id", "fecha"], name: "idx_agencia_movimiento_agencia_fecha")]
#[
    ApiResource(
        operations: [],
        paginationType: "page",
        order: ["fecha" => "DESC"],
        graphQlOperations: [
            new Query(),
            new QueryCollection(filters: ["order.filter", ColumnaFilter::class]),
        ],
    ),
]
class AgenciaMovimiento
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Agencia $agencia;

    #[ORM\Column(length: 20, enumType: TipoMovimientoAgencia::class)]
    private TipoMovimientoAgencia $tipo;

    /** Centavos con signo: positivo acredita, negativo debita. */
    #[ORM\Column(type: "bigint")]
    private int|string $monto;

    /** Saldo de la agencia tras aplicar el movimiento (centavos). */
    #[ORM\Column(type: "bigint")]
    private int|string $saldoResultante;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Usuario $usuario = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?BoletoVenta $boletoVenta = null;

    /** Número de boleta del depósito u otra referencia externa. */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $referencia = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $observacion = null;

    #[ORM\Column]
    private \DateTimeImmutable $fecha;

    public function __construct(
        Agencia $agencia,
        TipoMovimientoAgencia $tipo,
        int $monto,
        int $saldoResultante,
        ?Usuario $usuario = null,
        ?BoletoVenta $boletoVenta = null,
        ?string $referencia = null,
        ?string $observacion = null,
    ) {
        $this->agencia = $agencia;
        $this->tipo = $tipo;
        $this->monto = $monto;
        $this->saldoResultante = $saldoResultante;
        $this->usuario = $usuario;
        $this->boletoVenta = $boletoVenta;
        $this->referencia = $referencia;
        $this->observacion = $observacion;
        $this->fecha = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAgencia(): Agencia
    {
        return $this->agencia;
    }

    public function getTipo(): TipoMovimientoAgencia
    {
        return $this->tipo;
    }

    public function getMonto(): int
    {
        return (int) $this->monto;
    }

    public function getSaldoResultante(): int
    {
        return (int) $this->saldoResultante;
    }

    public function getUsuario(): ?Usuario
    {
        return $this->usuario;
    }

    public function getBoletoVenta(): ?BoletoVenta
    {
        return $this->boletoVenta;
    }

    public function getReferencia(): ?string
    {
        return $this->referencia;
    }

    public function getObservacion(): ?string
    {
        return $this->observacion;
    }

    public function getFecha(): \DateTimeImmutable
    {
        return $this->fecha;
    }

    public function getLabel(): string
    {
        return sprintf("%s %s", $this->tipo->value, number_format($this->getMonto() / 100, 2));
    }
}
