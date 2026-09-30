<?php

namespace App\Entity;

use App\Repository\AsientoRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AsientoRepository::class)]
class Asiento
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $numero = null;

    #[ORM\Column]
    private ?int $asiento_id = null;

    #[ORM\Column(nullable: true)]
    private ?float $precio = null;

    #[ORM\ManyToOne(inversedBy: 'asientos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SalidaReservacion $salidaReservacion = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $boleto_sistema_id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumero(): ?int
    {
        return $this->numero;
    }

    public function setNumero(int $numero): self
    {
        $this->numero = $numero;

        return $this;
    }

    public function getAsientoId(): ?int
    {
        return $this->asiento_id;
    }

    public function setAsientoId(int $asiento_id): self
    {
        $this->asiento_id = $asiento_id;

        return $this;
    }

    public function getPrecio(): ?float
    {
        return $this->precio;
    }

    public function setPrecio(?float $precio): self
    {
        $this->precio = $precio;

        return $this;
    }

    public function getSalidaReservacion(): ?SalidaReservacion
    {
        return $this->salidaReservacion;
    }

    public function setSalidaReservacion(?SalidaReservacion $salidaReservacion): self
    {
        $this->salidaReservacion = $salidaReservacion;

        return $this;
    }

    public function __toString()
    {
        return $this->asiento_id;
    }

    public function getBoletoSistemaId(): ?string
    {
        return $this->boleto_sistema_id;
    }

    public function setBoletoSistemaId(?string $boleto_sistema_id): self
    {
        $this->boleto_sistema_id = $boleto_sistema_id;

        return $this;
    }
}
