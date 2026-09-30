<?php

namespace App\EntitySistemaFdn;

use App\Repository\BoletoPaginaTempRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BoletoPaginaTempRepository::class)]
class BoletoPaginaTemp
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true, name: "reservacion_id")]
    private ?string $reservacion = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true, name: "salida_id")]
    private ?string $salida = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fecha_creacion = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $fecha_actualizacion = null;

    #[ORM\Column(nullable: true)]
    private ?bool $regreso = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReservacion()
    {
        return $this->reservacion;
    }

    public function setReservacion($reservacion): self
    {
        $this->reservacion = $reservacion;

        return $this;
    }

    public function getSalida(): ?string
    {
        return $this->salida;
    }

    public function setSalida(?string $salida): self
    {
        $this->salida = $salida;

        return $this;
    }

    public function getFechaCreacion(): ?\DateTimeInterface
    {
        return $this->fecha_creacion;
    }

    public function setFechaCreacion(\DateTimeInterface $fecha_creacion): self
    {
        $this->fecha_creacion = $fecha_creacion;

        return $this;
    }

    public function getFechaActualizacion(): ?\DateTimeInterface
    {
        return $this->fecha_actualizacion;
    }

    public function setFechaActualizacion(\DateTimeInterface $fecha_actualizacion): self
    {
        $this->fecha_actualizacion = $fecha_actualizacion;

        return $this;
    }

    /**
     * Get the value of regreso
     */
    public function getRegreso()
    {
        return $this->regreso;
    }

    /**
     * Set the value of regreso
     *
     * @return  self
     */
    public function setRegreso($regreso)
    {
        $this->regreso = $regreso;

        return $this;
    }
}
