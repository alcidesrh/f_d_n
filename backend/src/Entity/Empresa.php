<?php

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ApiResourceNoPagination]
class Empresa extends Base
{
    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $nit = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $direccion = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telefono = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    /** Afiliación al IVA ante la SAT (`GEN`, `PEQ`, …): va en cada factura. */
    #[ORM\Column(length: 5, options: ["default" => "GEN"])]
    private string $afiliacionIva = "GEN";

    /**
     * Frases de la SAT que lleva cada factura, `tipo-escenario` separadas por
     * coma (p. ej. `1-1,2-1`: sujeto a pagos trimestrales ISR y agente de
     * retención del IVA). Deben coincidir con las registradas en la SAT.
     */
    #[ORM\Column(length: 100, options: ["default" => "1-1"])]
    private string $frasesFel = "1-1";

    #[ORM\OneToMany(targetEntity: Bus::class, mappedBy: "empresa")]
    private Collection $buses;

    public function __construct()
    {
        $this->buses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;

        return $this;
    }

    public function getNit(): ?string
    {
        return $this->nit;
    }

    public function setNit(?string $nit): static
    {
        $this->nit = $nit;

        return $this;
    }

    public function getDireccion(): ?string
    {
        return $this->direccion;
    }

    public function setDireccion(?string $direccion): static
    {
        $this->direccion = $direccion;

        return $this;
    }

    public function getTelefono(): ?string
    {
        return $this->telefono;
    }

    public function setTelefono(?string $telefono): static
    {
        $this->telefono = $telefono;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getAfiliacionIva(): string
    {
        return $this->afiliacionIva;
    }

    public function setAfiliacionIva(string $afiliacionIva): static
    {
        $this->afiliacionIva = strtoupper(trim($afiliacionIva));

        return $this;
    }

    public function getFrasesFel(): string
    {
        return $this->frasesFel;
    }

    public function setFrasesFel(string $frasesFel): static
    {
        $this->frasesFel = $frasesFel;

        return $this;
    }

    /**
     * Frases como pares `[tipo, escenario]`.
     *
     * @return list<array{0: int, 1: int}>
     */
    public function frases(): array
    {
        $frases = [];
        foreach (explode(",", $this->frasesFel) as $par) {
            if (preg_match('/^\s*(\d+)\s*-\s*(\d+)\s*$/', $par, $m)) {
                $frases[] = [(int) $m[1], (int) $m[2]];
            }
        }

        return $frases;
    }

    public function getBuses(): Collection
    {
        return $this->buses;
    }
}
