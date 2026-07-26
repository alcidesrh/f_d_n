<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Attribute\ApiResourceNoPagination;
use App\Repository\VueRouteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VueRouteRepository::class)]
#[ApiResourceNoPagination]
class VueRoute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

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

    /**
     * @var Collection<int, Role>
     */
    #[ORM\ManyToMany(targetEntity: Role::class)]
    private Collection $roles;

    /**
     * @var Collection<int, Usuario>
     */
    #[ORM\ManyToMany(targetEntity: Usuario::class)]
    #[ORM\JoinTable(name: 'vue_route_usuario_permitidos')]
    private Collection $usuariosPermitidos;

    /**
     * @var Collection<int, Usuario>
     */
    #[ORM\ManyToMany(targetEntity: Usuario::class)]
    #[ORM\JoinTable(name: 'vue_route_usuario_denegados')]
    private Collection $usuariosDenegados;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'hijos')]
    private ?self $vueRoute = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'vueRoute')]
    private Collection $hijos;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
        $this->usuariosPermitidos = new ArrayCollection();
        $this->usuariosDenegados = new ArrayCollection();
        $this->hijos = new ArrayCollection();
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

    /**
     * @return Collection<int, Role>
     */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    public function addRole(Role $role): static
    {
        if (!$this->roles->contains($role)) {
            $this->roles->add($role);
        }

        return $this;
    }

    public function removeRole(Role $role): static
    {
        $this->roles->removeElement($role);

        return $this;
    }

    /**
     * @return Collection<int, Usuario>
     */
    public function getUsuariosPermitidos(): Collection
    {
        return $this->usuariosPermitidos;
    }

    public function addUsuariosPermitido(Usuario $usuariosPermitido): static
    {
        if (!$this->usuariosPermitidos->contains($usuariosPermitido)) {
            $this->usuariosPermitidos->add($usuariosPermitido);
        }

        return $this;
    }

    public function removeUsuariosPermitido(Usuario $usuariosPermitido): static
    {
        $this->usuariosPermitidos->removeElement($usuariosPermitido);

        return $this;
    }

    /**
     * @return Collection<int, Usuario>
     */
    public function getUsuariosDenegados(): Collection
    {
        return $this->usuariosDenegados;
    }

    public function addUsuariosDenegado(Usuario $usuariosDenegado): static
    {
        if (!$this->usuariosDenegados->contains($usuariosDenegado)) {
            $this->usuariosDenegados->add($usuariosDenegado);
        }

        return $this;
    }

    public function removeUsuariosDenegado(Usuario $usuariosDenegado): static
    {
        $this->usuariosDenegados->removeElement($usuariosDenegado);

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
