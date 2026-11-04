<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Acceso\Recurso;
use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ruta del frontend, sincronizada desde su router (`SyncVueRoutesController`)
 * con lo que exige su pantalla (`meta.capacidad` y `meta.recurso`): con eso
 * el backend poda los menús y la administración de cada usuario
 * (`App\Navigation\Pantallas`, ADR-049). No autoriza nada: lo que la
 * pantalla hace, la API lo exige igual (ADR-039).
 */
#[ORM\Entity]
#[ApiResourceNoPagination]
#[Recurso(nombre: "Rutas del frontend", area: "Configuración", lecturaLibre: true, soloLectura: true)]
class VueRoute extends Base
{
    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $vueRouteName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $path = null;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private ?array $params = null;

    /** @var list<string> alguna de estas capacidades (`meta.capacidad`); vacía, cualquiera con sesión */
    #[ORM\Column(type: Types::JSON, options: ['default' => '[]'])]
    private array $capacidades = [];

    /**
     * Pantalla del CRUD genérico (`meta.recurso`): de qué parámetro sale la
     * entidad y qué exige sobre ella (`App\Navigation\Pantalla`).
     *
     * @var array{parametro: string, operacion: string, registro?: string, selector?: string}|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $recurso = null;

    #[ORM\ManyToOne]
    private ?Icon $icon = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: "hijos")]
    private ?self $vueRoute = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: "vueRoute")]
    private Collection $hijos;

    public function __construct()
    {
        $this->hijos = new ArrayCollection();
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

    public function getVueRouteName(): ?string
    {
        return $this->vueRouteName;
    }

    public function setVueRouteName(?string $vueRouteName): static
    {
        $this->vueRouteName = $vueRouteName;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getParams(): ?array
    {
        return $this->params;
    }

    public function setParams(?array $params): static
    {
        $this->params = $params;

        return $this;
    }

    /** @return list<string> */
    public function getCapacidades(): array
    {
        return $this->capacidades;
    }

    /** @param list<string> $capacidades */
    public function setCapacidades(array $capacidades): static
    {
        $this->capacidades = array_values(array_unique($capacidades));

        return $this;
    }

    /** @return array{parametro: string, operacion: string, registro?: string, selector?: string}|null */
    public function getRecurso(): ?array
    {
        return $this->recurso;
    }

    /** @param array{parametro: string, operacion: string, registro?: string, selector?: string}|null $recurso */
    public function setRecurso(?array $recurso): static
    {
        $this->recurso = $recurso;

        return $this;
    }

    public function getIcon(): ?Icon
    {
        return $this->icon;
    }

    public function setIcon(?Icon $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getVueRoute(): ?self
    {
        return $this->vueRoute;
    }

    public function setVueRoute(?self $vueRoute): static
    {
        $this->vueRoute = $vueRoute;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getHijos(): Collection
    {
        return $this->hijos;
    }

    public function addHijo(self $hijo): static
    {
        if (!$this->hijos->contains($hijo)) {
            $this->hijos->add($hijo);
            $hijo->setVueRoute($this);
        }

        return $this;
    }

    public function removeHijo(self $hijo): static
    {
        if ($this->hijos->removeElement($hijo)) {
            // set the owning side to null (unless already changed)
            if ($hijo->getVueRoute() === $this) {
                $hijo->setVueRoute(null);
            }
        }

        return $this;
    }
}
