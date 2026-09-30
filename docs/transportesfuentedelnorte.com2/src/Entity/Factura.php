<?php

namespace App\Entity;

use App\Repository\FacturaRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FacturaRepository::class)]
class Factura {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dte = null;

    #[ORM\Column(nullable: true)]
    private ?string $uuid = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $fecha = null;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?string $id_sistema = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pdf = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $serie = null;

    #[ORM\OneToOne(mappedBy: 'factura', cascade: ['persist'])]
    private ?Reservacion $reservacion = null;

    public function __construct($data = null) {
        if ($data) {
            $this->dte = $data['NumeroDTE'];
            $this->serie = $data['SerieDTE'];
            $this->fecha = new DateTime($data['FechaEmisionDTE']);
            $this->uuid = $data['AutorizacionUUID'];
        }
    }
    public function setData($data = null) {
        if ($data) {
            $this->dte = $data['NumeroDTE'];
            $this->serie = $data['SerieDTE'];
            $this->fecha = new DateTime($data['FechaEmisionDTE']);
            $this->uuid = $data['AutorizacionUUID'];
        }
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getDte(): ?string {
        return $this->dte;
    }

    public function setDte(string $dte): self {
        $this->dte = $dte;

        return $this;
    }

    public function getUuid(): ?string {
        return $this->uuid;
    }

    public function setUuid(?string $uuid): self {
        $this->uuid = $uuid;

        return $this;
    }

    public function getFecha(): ?\DateTimeInterface {
        return $this->fecha;
    }

    public function setFecha(\DateTimeInterface $fecha): self {
        $this->fecha = $fecha;

        return $this;
    }

    public function getIdSistema(): ?string {
        return $this->id_sistema;
    }

    public function setIdSistema(?string $id_sistema): self {
        $this->id_sistema = $id_sistema;

        return $this;
    }

    public function getPdf(): ?string {
        return $this->pdf;
    }

    public function setPdf(?string $pdf): self {
        $this->pdf = $pdf;

        return $this;
    }

    public function getSerie(): ?string {
        return $this->serie;
    }

    public function setSerie(?string $serie): self {
        $this->serie = $serie;

        return $this;
    }

    public function getReservacion(): ?Reservacion {
        return $this->reservacion;
    }

    public function setReservacion(?Reservacion $reservacion): self {
        // unset the owning side of the relation if necessary
        if ($reservacion === null && $this->reservacion !== null) {
            $this->reservacion->setFactura(null);
        }

        // set the owning side of the relation if necessary
        if ($reservacion !== null && $reservacion->getFactura() !== $this) {
            $reservacion->setFactura($this);
        }

        $this->reservacion = $reservacion;

        return $this;
    }

    public function getData() {
        return [
            'dte' => $this->getDte(),
            'serie' => $this->getSerie(),
            'uuid' => $this->getUuid(),
            'fecha' => $this->fecha->format('Y-m-d H:i:s'),
        ];
    }

    public function getPath() {
        return $this->pdf ? 'facturas/' . $this->pdf : null;
    }
}
