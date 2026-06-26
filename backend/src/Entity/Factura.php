<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Entity\Base\Traits\TimestampableEntityTrait;
use App\Repository\FacturaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: FacturaRepository::class)]
#[ApiResource]
class Factura {
    use TimestampableEntityTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $dte = null;

    #[ORM\Column(type: 'uuid')]
    private ?Uuid $uuid = null;

    #[ORM\Column(length: 255)]
    private ?string $serie = null;

    #[ORM\Column]
    private ?\DateTime $fecha = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $emisorNit = null;

    #[ORM\Column(length: 255)]
    private ?string $emisorNombre = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $establecimientoCodigo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emisorNombreComercial = null;

    #[ORM\Column(length: 25)]
    private ?string $receptopNit = null;

    #[ORM\Column(length: 255)]
    private ?string $receptorNombre = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getDte(): ?int {
        return $this->dte;
    }

    public function setDte(int $dte): static {
        $this->dte = $dte;

        return $this;
    }

    public function getUuid(): ?Uuid {
        return $this->uuid;
    }

    public function setUuid(Uuid $uuid): static {
        $this->uuid = $uuid;

        return $this;
    }

    public function getSerie(): ?string {
        return $this->serie;
    }

    public function setSerie(string $serie): static {
        $this->serie = $serie;

        return $this;
    }

    public function getFecha(): ?\DateTime {
        return $this->fecha;
    }

    public function setFecha(\DateTime $fecha): static {
        $this->fecha = $fecha;

        return $this;
    }

    public function getEmisorNit(): ?string {
        return $this->emisorNit;
    }

    public function setEmisorNit(?string $emisorNit): static {
        $this->emisorNit = $emisorNit;

        return $this;
    }

    public function getEmisorNombre(): ?string {
        return $this->emisorNombre;
    }

    public function setEmisorNombre(string $emisorNombre): static {
        $this->emisorNombre = $emisorNombre;

        return $this;
    }

    public function getEstablecimientoCodigo(): ?string {
        return $this->establecimientoCodigo;
    }

    public function setEstablecimientoCodigo(?string $establecimientoCodigo): static {
        $this->establecimientoCodigo = $establecimientoCodigo;

        return $this;
    }

    public function getEmisorNombreComercial(): ?string {
        return $this->emisorNombreComercial;
    }

    public function setEmisorNombreComercial(?string $emisorNombreComercial): static {
        $this->emisorNombreComercial = $emisorNombreComercial;

        return $this;
    }

    public function getReceptopNit(): ?string {
        return $this->receptopNit;
    }

    public function setReceptopNit(string $receptopNit): static {
        $this->receptopNit = $receptopNit;

        return $this;
    }

    public function getReceptorNombre(): ?string {
        return $this->receptorNombre;
    }

    public function setReceptorNombre(string $receptorNombre): static {
        $this->receptorNombre = $receptorNombre;

        return $this;
    }
}
