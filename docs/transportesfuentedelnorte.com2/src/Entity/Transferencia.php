<?php

namespace App\Entity;

use App\Repository\TransferenciaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransferenciaRepository::class)]
class Transferencia extends Reservacion {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    private ?ClienteReservacion $cliente = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $nota = null;

    #[ORM\Column(nullable: true)]
    private ?float $cantidad = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $moneda = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $status = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Tarjeta $tarjeta = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $transaccion_id = null;

    public function getId(): ?int {
        return $this->id;
    }


    public function getCliente(): ?ClienteReservacion {
        return $this->cliente;
    }

    public function setCliente(?ClienteReservacion $cliente): static {
        $this->cliente = $cliente;

        return $this;
    }

    public function getNota(): ?string {
        return $this->nota;
    }

    public function setNota(?string $nota): static {
        $this->nota = $nota;

        return $this;
    }

    public function getCantidad(): ?float {
        return $this->cantidad;
    }

    public function getPrecioCobrar(): ?float {
        return $this->cantidad;
    }

    public function setCantidad(?float $cantidad): static {
        $this->cantidad = $cantidad;

        return $this;
    }

    public function getMoneda(): ?string {
        return $this->moneda;
    }

    public function setMoneda(?string $moneda): static {
        $this->moneda = $moneda;

        return $this;
    }

    public function getStatus(): ?string {
        return $this->status;
    }

    public function setStatus(?string $status): static {
        $this->status = $status;

        return $this;
    }

    public function getTarjeta(): ?Tarjeta {
        return $this->tarjeta;
    }

    public function setTarjeta(?Tarjeta $tarjeta): static {
        $this->tarjeta = $tarjeta;

        return $this;
    }

    public function getTransaccionId(): ?string {
        return $this->transaccion_id;
    }

    public function setTransaccionId(?string $transaccion_id): static {
        $this->transaccion_id = $transaccion_id;

        return $this;
    }
    public function setStatusCybersources(string $status_cybersources): self {
        $this->status = $status_cybersources;

        return $this;
    }
}
