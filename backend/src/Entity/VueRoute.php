<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Acceso\Recurso;
use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use App\Repository\VueRouteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ruta del frontend, sincronizada desde su router (`SyncVueRoutesController`).
 * No decide accesos: cada pantalla exige su capacidad en el backend (ADR-039).
 */
#[ORM\Entity(repositoryClass: VueRouteRepository::class)]
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
