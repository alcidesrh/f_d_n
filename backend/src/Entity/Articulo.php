<?php

declare(strict_types=1);

namespace App\Entity;

use App\Acceso\Recurso;
use App\Attribute\ApiResourcePaginationPage;
use App\Entity\Base\Base;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Lo que se guarda y se gasta (ADR-071): papel térmico, tóner, aceite,
 * filtros, una llanta. El catálogo es de todas las empresas; cuánto hay de
 * cada uno es por almacén (`Existencia`).
 *
 * `serializado`: se sigue uno por uno (computadoras, impresoras, terminales
 * de pago, GPS, llantas). Por ahora se cuenta como los demás; el
 * seguimiento por serie llega con los activos.
 *
 * `tipoAfectacion`: el tipo que crea su faltante al bajar del mínimo; si no
 * dice, `insumo_agotado` en estaciones y oficinas, `repuesto_faltante` en
 * talleres y buses.
 */
#[ORM\Entity]
#[ApiResourcePaginationPage]
#[Recurso(nombre: "Artículos de inventario", area: "Logística", lecturaLibre: true, editar: "inventario.articulos", eliminar: "inventario.articulos")]
class Articulo extends Base
{
    #[Assert\NotBlank(message: "El código es obligatorio.")]
    #[Assert\Length(max: 40)]
    #[ORM\Column(length: 40, unique: true)]
    private string $codigo = "";

    #[Assert\NotBlank(message: "El nombre es obligatorio.")]
    #[Assert\Length(max: 120)]
    #[ORM\Column(length: 120, unique: true)]
    private string $nombre = "";

    /** En qué se cuenta: rollo, caja, litro, unidad. */
    #[Assert\NotBlank(message: "Indique en qué se cuenta (rollo, caja, litro…).")]
    #[Assert\Length(max: 20)]
    #[ORM\Column(length: 20, options: ["default" => "unidad"])]
    private string $unidad = "unidad";

    #[Assert\Length(max: 60)]
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $categoria = null;

    #[ORM\Column(options: ["default" => false])]
    private bool $serializado = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: "SET NULL")]
    private ?TipoAfectacion $tipoAfectacion = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $descripcion = null;

    /** Uno inactivo no se ofrece para mover; su historia se conserva. */
    #[ORM\Column(options: ["default" => true])]
    private bool $activo = true;

    public function getCodigo(): string
    {
        return $this->codigo;
    }

    public function setCodigo(string $codigo): static
    {
        $this->codigo = trim($codigo);

        return $this;
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

    public function getUnidad(): string
    {
        return $this->unidad;
    }

    public function setUnidad(string $unidad): static
    {
        $this->unidad = trim($unidad);

        return $this;
    }

    public function getCategoria(): ?string
    {
        return $this->categoria;
    }

    public function setCategoria(?string $categoria): static
    {
        $this->categoria = $categoria === null || trim($categoria) === "" ? null : trim($categoria);

        return $this;
    }

    public function isSerializado(): bool
    {
        return $this->serializado;
    }

    public function setSerializado(bool $serializado): static
    {
        $this->serializado = $serializado;

        return $this;
    }

    public function getTipoAfectacion(): ?TipoAfectacion
    {
        return $this->tipoAfectacion;
    }

    public function setTipoAfectacion(?TipoAfectacion $tipoAfectacion): static
    {
        $this->tipoAfectacion = $tipoAfectacion;

        return $this;
    }

    public function getDescripcion(): ?string
    {
        return $this->descripcion;
    }

    public function setDescripcion(?string $descripcion): static
    {
        $this->descripcion = $descripcion;

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
