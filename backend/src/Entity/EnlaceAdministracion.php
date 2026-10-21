<?php

declare(strict_types=1);

namespace App\Entity;

use App\Administracion\Destino;
use App\Entity\Base\Traits\AccesoPorUsuarioTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Enlace de una caja de la administración: lo que abre al pulsarlo
 * (`abrir`: ruta del frontend con parámetros o un path a mano) y, si tiene,
 * su "Agregar nuevo" (`agregar`). `claves` son otros nombres por los que se
 * busca (los del legado). `activo` en falso lo oculta (solo se ve al
 * configurar); quién lo ve, por roles y usuarios, además de la caja.
 */
#[ORM\Entity]
#[ORM\Index(columns: ["seccion_id", "posicion"], name: "idx_enlace_administracion_seccion")]
class EnlaceAdministracion
{
    use AccesoPorUsuarioTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: "enlaces")]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private SeccionAdministracion $seccion;

    #[ORM\Column(length: 80)]
    private string $etiqueta;

    #[ORM\Column(length: 80)]
    private string $icono;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON, options: ["default" => "[]"])]
    private array $claves = [];

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $ruta = null;

    /** @var array<string, string>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $parametros = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $path = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $agregarRuta = null;

    /** @var array<string, string>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $agregarParametros = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $agregarPath = null;

    #[ORM\Column]
    private int $posicion = 0;

    #[ORM\Column]
    private bool $activo = true;

    public function __construct(SeccionAdministracion $seccion, string $etiqueta, string $icono, Destino $abrir)
    {
        $this->seccion = $seccion;
        $this->etiqueta = $etiqueta;
        $this->icono = $icono;
        $this->setAbrir($abrir);
        $this->roles = new ArrayCollection();
        $this->usuarios = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeccion(): SeccionAdministracion
    {
        return $this->seccion;
    }

    public function setSeccion(SeccionAdministracion $seccion): static
    {
        $this->seccion = $seccion;

        return $this;
    }

    public function getEtiqueta(): string
    {
        return $this->etiqueta;
    }

    public function getIcono(): string
    {
        return $this->icono;
    }

    /** @param list<string> $claves */
    public function describir(string $etiqueta, string $icono, array $claves): static
    {
        $this->etiqueta = $etiqueta;
        $this->icono = $icono;
        $this->claves = $claves;

        return $this;
    }

    /** @return list<string> */
    public function getClaves(): array
    {
        return $this->claves;
    }

    public function getAbrir(): Destino
    {
        return $this->ruta !== null ? Destino::ruta($this->ruta, $this->parametros ?? []) : Destino::path((string) $this->path);
    }

    public function setAbrir(Destino $destino): static
    {
        $this->ruta = $destino->ruta;
        $this->parametros = $destino->ruta !== null ? $destino->parametros : null;
        $this->path = $destino->path;

        return $this;
    }

    public function getAgregar(): ?Destino
    {
        return match (true) {
            $this->agregarRuta !== null => Destino::ruta($this->agregarRuta, $this->agregarParametros ?? []),
            $this->agregarPath !== null => Destino::path($this->agregarPath),
            default => null,
        };
    }

    public function setAgregar(?Destino $destino): static
    {
        $this->agregarRuta = $destino?->ruta;
        $this->agregarParametros = $destino?->ruta !== null ? $destino->parametros : null;
        $this->agregarPath = $destino?->path;

        return $this;
    }

    public function getPosicion(): int
    {
        return $this->posicion;
    }

    public function setPosicion(int $posicion): static
    {
        $this->posicion = $posicion;

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
