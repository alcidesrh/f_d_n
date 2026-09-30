<?php

namespace App\Entity;

use App\Repository\ClienteReservacionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClienteReservacionRepository::class)]
class ClienteReservacion {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $nit = null;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telefono = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $apellido = null;

    #[ORM\Column(length: 255)]
    private ?string $direccion = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $codigo_postal = null;

    #[ORM\Column(nullable: true)]
    private ?int $cliente_id = null;

    #[ORM\ManyToOne]
    private ?Countries $pais = null;

    #[ORM\ManyToOne]
    private ?States $provincia = null;

    #[ORM\ManyToOne]
    private ?Cities $ciudad = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nombre_factura  = null;

    public function __construct(?Countries $pais = null) {
        $this->pais = $pais;
        $this->nit = 'CF';
    }

    public function __toString() {
        return $this->getNombreCompleto();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getNit(): ?string {
        return $this->nit;
    }

    public function getNitOrCF($default = null) {
        return $this->nit ? \strtoupper(\preg_replace("/[^A-Za-z0-9 ]/", '', $this->nit)) : $default;
    }

    public function setNit(?string $nit): self {
        $this->nit = $nit;

        return $this;
    }

    public function getNombre(): ?string {
        return $this->nombre;
    }

    public function setNombre(string $nombre): self {
        $this->nombre = $nombre;
        $this->nombre_factura = $nombre;

        return $this;
    }

    public function getNombreFactura($nombre_completo = false): ?string {
        if ($this->nombre_factura && !$nombre_completo) {
            return $this->nombre_factura ?? $this->nombre;
        }
        return $nombre_completo ? $this->getNombreCompleto() : $this->nombre;
    }

    public function setNombreFactura(?string $nombre): self {
        $this->nombre_factura = $nombre;

        return $this;
    }

    public function getEmail(): ?string {
        return $this->email;
    }

    public function setEmail(?string $email): self {
        $this->email = $email;

        return $this;
    }

    public function getTelefono(): ?string {
        return $this->telefono;
    }

    public function setTelefono(?string $telefono): self {
        $this->telefono = $telefono;

        return $this;
    }

    public function getApellido(): ?string {
        return $this->apellido;
    }

    public function setApellido(?string $apellido): self {
        $this->apellido = $apellido;

        return $this;
    }

    public function getDireccion(): ?string {
        return $this->direccion;
    }

    public function setDireccion(string $direccion): self {
        $this->direccion = $direccion;

        return $this;
    }

    public function getCodigoPostal(): ?string {
        return $this->codigo_postal;
    }

    public function setCodigoPostal(?string $codigo_postal): self {
        $this->codigo_postal = $codigo_postal;

        return $this;
    }

    public function getClienteId(): ?int {
        return $this->cliente_id;
    }

    public function setClienteId(?int $cliente_id): self {
        $this->cliente_id = $cliente_id;

        return $this;
    }

    public function getNombreCompleto() {
        return $this->nombre . ' ' . $this->apellido;
    }

    public function getPais(): ?Countries {
        return $this->pais;
    }

    public function getPaisNombre(): ?Countries {
        return $this->pais?->getName();
    }


    public function setPais(?Countries $pais): self {
        $this->pais = $pais;

        return $this;
    }

    public function getProvincia(): ?States {
        return $this->provincia;
    }

    public function setProvincia(?States $provincia): self {
        $this->provincia = $provincia;

        return $this;
    }

    public function getCiudad(): ?Cities {
        return $this->ciudad;
    }

    public function setCiudad(?Cities $ciudad): self {
        $this->ciudad = $ciudad;

        return $this;
    }
}
