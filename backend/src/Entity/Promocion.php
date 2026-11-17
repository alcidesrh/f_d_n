<?php

declare(strict_types=1);

namespace App\Entity;

use App\Acceso\Recurso;
use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Un descuento por un tiempo (ADR-066): «20 % para viajar del 1 al 15 de
 * diciembre». Vale para las salidas de esas fechas y, si se fijan, de esa
 * empresa, ruta o clase de bus. Sin código se aplica sola; con código, solo
 * a la venta que lo escribe.
 */
#[ORM\Entity]
#[ApiResourcePaginationPage]
#[Recurso(nombre: "Promociones", area: "Venta", empresa: "empresa")]
class Promocion extends Base
{
    #[Assert\NotBlank]
    #[ORM\Column(length: 80)]
    private string $nombre = "";

    /** Lo que escribe la taquilla para aplicarla; null = se aplica sola. Se guarda en mayúsculas. */
    #[Assert\Regex(pattern: '/^[A-Z0-9_-]{3,20}$/', message: "El código lleva de 3 a 20 letras, números, guiones o guiones bajos.")]
    #[ORM\Column(length: 20, unique: true, nullable: true)]
    private ?string $codigo = null;

    #[Assert\Range(min: 1, max: 100)]
    #[ORM\Column(type: "smallint")]
    private int $porciento = 0;

    /** Primer día de viaje que cubre (fecha de la salida). */
    #[Assert\NotNull(message: "Indique desde qué día vale.")]
    #[ORM\Column(type: "date")]
    private ?\DateTime $desde = null;

    /** Último día de viaje que cubre (incluido). */
    #[Assert\NotNull(message: "Indique hasta qué día vale.")]
    #[ORM\Column(type: "date")]
    private ?\DateTime $hasta = null;

    /** Solo las salidas de esta empresa; null = de todas. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    private ?Empresa $empresa = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    private ?Ruta $ruta = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    private ?BusClase $busClase = null;

    #[ORM\Column(options: ["default" => true])]
    private bool $activa = true;

    #[Assert\Callback]
    public function validarFechas(ExecutionContextInterface $contexto): void
    {
        if ($this->desde !== null && $this->hasta !== null && $this->hasta < $this->desde) {
            $contexto->buildViolation("La promoción termina antes de empezar.")->atPath("hasta")->addViolation();
        }
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = trim($nombre);

        return $this;
    }

    public function getCodigo(): ?string
    {
        return $this->codigo;
    }

    public function setCodigo(?string $codigo): static
    {
        $codigo = $codigo === null ? "" : mb_strtoupper(trim($codigo));
        $this->codigo = $codigo === "" ? null : $codigo;

        return $this;
    }

    public function getPorciento(): int
    {
        return $this->porciento;
    }

    public function setPorciento(int $porciento): static
    {
        $this->porciento = $porciento;

        return $this;
    }

    public function getDesde(): ?\DateTime
    {
        return $this->desde;
    }

    public function setDesde(?\DateTime $desde): static
    {
        $this->desde = $desde;

        return $this;
    }

    public function getHasta(): ?\DateTime
    {
        return $this->hasta;
    }

    public function setHasta(?\DateTime $hasta): static
    {
        $this->hasta = $hasta;

        return $this;
    }

    public function getEmpresa(): ?Empresa
    {
        return $this->empresa;
    }

    public function setEmpresa(?Empresa $empresa): static
    {
        $this->empresa = $empresa;

        return $this;
    }

    public function getRuta(): ?Ruta
    {
        return $this->ruta;
    }

    public function setRuta(?Ruta $ruta): static
    {
        $this->ruta = $ruta;

        return $this;
    }

    public function getBusClase(): ?BusClase
    {
        return $this->busClase;
    }

    public function setBusClase(?BusClase $busClase): static
    {
        $this->busClase = $busClase;

        return $this;
    }

    public function isActiva(): bool
    {
        return $this->activa;
    }

    public function setActiva(bool $activa): static
    {
        $this->activa = $activa;

        return $this;
    }
}
