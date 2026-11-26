<?php

declare(strict_types=1);

namespace App\Entity;

use App\Acceso\Recurso;
use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use App\Entity\Enum\TipoAlmacen;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Un lugar donde hay existencias (ADR-071): una estación, un taller, una
 * oficina o un bus (la llanta de repuesto, el extintor). No hay bodega
 * central; lo que no se consigue en el lugar se envía desde otro almacén,
 * en general de la capital.
 *
 * Es de una empresa (las estaciones son compartidas: cada empresa tiene el
 * suyo en la misma estación). Donde está —empresa y estación— decide quién
 * lo ve y quién lo mueve. Uno sin estación (un bus, un taller fuera de una
 * estación) solo lo alcanza quien tiene todas las estaciones de su empresa.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: "uniq_almacen_empresa_nombre", columns: ["empresa_id", "nombre"])]
#[ApiResourceNoPagination]
#[Recurso(nombre: "Almacenes", area: "Logística", lecturaLibre: true, empresa: "empresa", editar: "inventario.articulos", eliminar: "inventario.articulos")]
class Almacen extends Base
{
    #[Assert\NotBlank(message: "El nombre es obligatorio.")]
    #[Assert\Length(max: 80)]
    #[ORM\Column(length: 80)]
    private string $nombre = "";

    #[ORM\Column(length: 12, enumType: TipoAlmacen::class, options: ["default" => "estacion"])]
    private TipoAlmacen $tipo = TipoAlmacen::ESTACION;

    #[Assert\NotNull(message: "Elija la empresa.")]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Empresa $empresa = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?Estacion $estacion = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    private ?Bus $bus = null;

    /** Uno inactivo no recibe ni envía; su historia se conserva (uno con existencias no se borra). */
    #[ORM\Column(options: ["default" => true])]
    private bool $activo = true;

    #[Assert\Callback]
    public function validarLugar(ExecutionContextInterface $contexto): void
    {
        if ($this->tipo === TipoAlmacen::ESTACION && $this->estacion === null) {
            $contexto->buildViolation("El almacén de una estación necesita la estación.")->atPath("estacion")->addViolation();
        }
        if ($this->tipo === TipoAlmacen::BUS && $this->bus === null) {
            $contexto->buildViolation("El almacén de un bus necesita el bus.")->atPath("bus")->addViolation();
        }
        if ($this->tipo !== TipoAlmacen::BUS && $this->bus !== null) {
            $contexto->buildViolation("Solo el almacén de un bus lleva bus.")->atPath("bus")->addViolation();
        }
        if ($this->bus !== null && $this->empresa !== null && $this->bus->getEmpresa() !== null && $this->bus->getEmpresa()->getId() !== $this->empresa->getId()) {
            $contexto->buildViolation("El bus es de otra empresa.")->atPath("bus")->addViolation();
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

    public function getTipo(): TipoAlmacen
    {
        return $this->tipo;
    }

    public function setTipo(TipoAlmacen $tipo): static
    {
        $this->tipo = $tipo;

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

    public function getEstacion(): ?Estacion
    {
        return $this->estacion;
    }

    public function setEstacion(?Estacion $estacion): static
    {
        $this->estacion = $estacion;

        return $this;
    }

    public function getBus(): ?Bus
    {
        return $this->bus;
    }

    public function setBus(?Bus $bus): static
    {
        $this->bus = $bus;

        return $this;
    }

    public function isActivo(): bool
    {
        return $this->activo;
    }

    public function setActivo(bool $activo): static
    {
        $this->activo = $activo;

        return $this;
    }
}
