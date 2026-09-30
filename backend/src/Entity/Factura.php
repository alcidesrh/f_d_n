<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use App\Entity\Embeddable\Precio;
use App\Entity\Base\Traits\TimestampableEntityTrait;
use App\Repository\FacturaRepository;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * DTE (factura electrónica) certificado de una venta: snapshot inmutable de
 * emisor, receptor y datos del certificador. Lo crea `App\Venta` con la
 * respuesta del certificador; la API solo lo lee.
 */
#[ORM\Entity(repositoryClass: FacturaRepository::class)]
#[
    ApiResource(
        operations: [new Get(), new GetCollection()],
        graphQlOperations: [new Query(), new QueryCollection()],
    ),
]
class Factura {
    use TimestampableEntityTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Número del DTE (hasta 10 dígitos). */
    #[ORM\Column(type: 'bigint')]
    private int|string|null $dte = null;

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

    /** Momento en que el certificador firmó el DTE. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $fechaCertificacion = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $certificadorNit = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $certificadorNombre = null;

    #[ORM\Embedded(class: Precio::class, columnPrefix: 'total_')]
    private ?Precio $total = null;

    /** Representación gráfica (PDF) del DTE en el portal del certificador. */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $urlPdf = null;

    /** XML certificado tal como lo devolvió el certificador (respaldo fiscal). */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $xml = null;

    public function getId(): ?int {
        return $this->id;
    }

    public function getDte(): ?int {
        return $this->dte !== null ? (int) $this->dte : null;
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

    public function getFechaCertificacion(): ?\DateTimeImmutable {
        return $this->fechaCertificacion;
    }

    public function setFechaCertificacion(?\DateTimeImmutable $fechaCertificacion): static {
        $this->fechaCertificacion = $fechaCertificacion;

        return $this;
    }

    public function getCertificadorNit(): ?string {
        return $this->certificadorNit;
    }

    public function setCertificadorNit(?string $certificadorNit): static {
        $this->certificadorNit = $certificadorNit;

        return $this;
    }

    public function getCertificadorNombre(): ?string {
        return $this->certificadorNombre;
    }

    public function setCertificadorNombre(?string $certificadorNombre): static {
        $this->certificadorNombre = $certificadorNombre;

        return $this;
    }

    public function getTotal(): ?Money {
        return $this->total?->toMoney();
    }

    public function setTotal(Money $total): static {
        $this->total = Precio::fromMoney($total);

        return $this;
    }

    public function getXml(): ?string {
        return $this->xml;
    }

    public function setXml(?string $xml): static {
        $this->xml = $xml;

        return $this;
    }

    public function getUrlPdf(): ?string {
        return $this->urlPdf;
    }

    public function setUrlPdf(?string $urlPdf): static {
        $this->urlPdf = $urlPdf;

        return $this;
    }
}
