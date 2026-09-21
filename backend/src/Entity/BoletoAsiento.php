<?php

namespace App\Entity;

use App\Entity\Embeddable\Precio;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Repository\BoletoAsientoRepository;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;

#[ORM\Entity(repositoryClass: BoletoAsientoRepository::class)]
#[
    ORM\UniqueConstraint(
        name: "uq_boleto_asiento_asiento_trayecto_recorrido",
        columns: ["asiento_id", "trayecto_id", "recorrido_id"],
    ),
]
class BoletoAsiento
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

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
    private ?Recorrido $recorrido = null;

    #[ORM\Embedded(class: Precio::class)]
    private ?Precio $precio = null;

    #[ORM\Column(type: "string", length: 50, nullable: true)]
    private ?string $legacyId = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getRecorrido(): ?Recorrido
    {
        return $this->recorrido;
    }

    public function setRecorrido(?Recorrido $recorrido): static
    {
        $this->recorrido = $recorrido;

        return $this;
    }

    public function getPrecio(): ?Money
    {
        return $this->precio;
    }

    public function setPrecio(Money $money): self
    {
        $this->precio = Precio::fromMoney($money);
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
