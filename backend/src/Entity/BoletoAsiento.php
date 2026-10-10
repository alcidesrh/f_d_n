<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use App\Entity\Base\Base;
use App\Entity\Embeddable\Precio;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Repository\BoletoAsientoRepository;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;

/**
 * Un asiento vendido. Solo lectura por la API: se crea, anula y reasigna por
 * `App\Venta` (ADR-021), que valida disponibilidad, tarifa y factura.
 *
 * La restricción única solo cuenta los boletos vivos: un asiento anulado o
 * reasignado se puede volver a vender en el mismo trayecto y salida.
 */
#[ORM\Entity(repositoryClass: BoletoAsientoRepository::class)]
#[
    ORM\UniqueConstraint(
        name: "uq_boleto_asiento_asiento_trayecto_salida",
        columns: ["asiento_id", "trayecto_id", "salida_id"],
        // Tal como lo devuelve PostgreSQL (`pg_get_expr`): así el esquema no marca diferencias.
        options: ["where" => "((estado)::text <> ALL ((ARRAY['anulado'::character varying, 'reasignado'::character varying])::text[]))"],
    ),
]
#[
    ApiResource(
        operations: [],
        paginationType: "page",
        order: ["id" => "DESC"],
        graphQlOperations: [
            new Query(),
            new QueryCollection(filters: ["order.filter"]),
        ],
    ),
]
class BoletoAsiento extends Base
{
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Asiento $asiento = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Trayecto $trayecto = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Cliente $cliente = null;

    #[ORM\Column(type: "string", length: 20, enumType: EstadoBoletoAsiento::class)]
    private EstadoBoletoAsiento $estado = EstadoBoletoAsiento::EMITIDO;

    #[ORM\ManyToOne(inversedBy: "asientos")]
    #[ORM\JoinColumn(nullable: false)]
    private ?BoletoVenta $boletoVenta = null;

    #[ORM\ManyToOne(inversedBy: "boletoAsientos")]
    #[ORM\JoinColumn(nullable: false)]
    private ?Salida $salida = null;

    #[ORM\Embedded(class: Precio::class)]
    private ?Precio $precio = null;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $legacyId = null;

    /** Boleto al que reemplaza cuando este nació de una reasignación. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?BoletoAsiento $reasignadoDe = null;

    /** Nota de taquilla (p. ej. "viaja con mascota", "se baja en el km 120"). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $observacion = null;

    /** "Boleto 15 · asiento 12": el texto que muestran los listados y el chat. */
    public function getLabel(): string
    {
        return sprintf("Boleto %d · asiento %s", $this->id ?? 0, $this->asiento?->getNumero() ?? "?");
    }

    public function getAsiento(): ?Asiento
    {
        return $this->asiento;
    }

    public function setAsiento(?Asiento $asiento): static
    {
        $this->asiento = $asiento;

        return $this;
    }

    public function getTrayecto(): ?Trayecto
    {
        return $this->trayecto;
    }

    public function setTrayecto(?Trayecto $trayecto): static
    {
        $this->trayecto = $trayecto;

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

    public function getEstado(): EstadoBoletoAsiento
    {
        return $this->estado;
    }

    /**
     * Transiciona el boleto al nuevo estado, validando la máquina de estados:
     * emitido -> chequeado -> transito -> finalizado (lineal),
     * y emitido -> anulado / emitido -> reasignado (solo desde emitido).
     *
     * @throws \DomainException si la transición no está permitida
     */
    public function setEstado(EstadoBoletoAsiento $estado): static
    {
        if (
            $estado !== $this->estado &&
            !$this->estado->puedeTransicionarA($estado)
        ) {
            throw new \DomainException(
                sprintf(
                    "Transición de BoletoAsiento inválida: %s -> %s",
                    $this->estado->value,
                    $estado->value,
                ),
            );
        }

        $this->estado = $estado;

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

    public function getSalida(): ?Salida
    {
        return $this->salida;
    }

    public function setSalida(?Salida $salida): static
    {
        $this->salida = $salida;

        return $this;
    }

    public function getPrecio(): ?Money
    {
        return $this->precio?->toMoney();
    }

    /** El precio ya formateado con su moneda (`Q 100.00`), para los listados de la API. */
    public function getImporte(): ?string
    {
        $precio = $this->getPrecio();
        if ($precio === null) {
            return null;
        }
        $moneda = $precio->getCurrency()->getCode();

        return sprintf("%s %s", $moneda === "GTQ" ? "Q" : $moneda, number_format((int) $precio->getAmount() / 100, 2));
    }

    public function setPrecio(Money $money): self
    {
        $this->precio = Precio::fromMoney($money);
        return $this;
    }

    public function getReasignadoDe(): ?BoletoAsiento
    {
        return $this->reasignadoDe;
    }

    public function setReasignadoDe(?BoletoAsiento $reasignadoDe): static
    {
        $this->reasignadoDe = $reasignadoDe;

        return $this;
    }

    public function getObservacion(): ?string
    {
        return $this->observacion;
    }

    public function setObservacion(?string $observacion): static
    {
        $this->observacion = $observacion;

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
}
