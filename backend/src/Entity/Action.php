<?php

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;


use App\Entity\Base\Base;
use App\Entity\Base\Traits\StatusTrait;
use App\Repository\ActionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActionRepository::class)]
#[ApiResourceNoPagination]
class Action extends Base {

    use StatusTrait;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ruta = null;

    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $nombre = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $codigo = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $recurso = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $operacion = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $grupo = null;

    /**
     * @var Collection<int, Role>
     */
    #[ORM\ManyToMany(targetEntity: Role::class, mappedBy: 'actions')]
    private Collection $roles;

    /**
     * @var Collection<int, Permiso>
     */
    #[ORM\ManyToMany(targetEntity: Permiso::class, mappedBy: 'actions')]
    private Collection $permisos;

    public function __construct() {
        parent::__construct();
        $this->roles = new ArrayCollection();
        $this->permisos = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getNombre(): ?string {
        return $this->nombre;
    }
    public function setNombre(string $nombre): static {
        $this->nombre = $nombre;
        return $this;
    }

    public function getCodigo(): ?string {
        return $this->codigo;
    }

    public function setCodigo(string $codigo): static {
        $this->codigo = $codigo;
        return $this;
    }

    public function getRecurso(): ?string {
        return $this->recurso;
    }

    public function setRecurso(string $recurso): static {
        $this->recurso = $recurso;
        return $this;
    }

    public function getOperacion(): ?string {
        return $this->operacion;
    }

    public function setOperacion(string $operacion): static {
        $this->operacion = $operacion;
        return $this;
    }

    public function getGrupo(): ?string {
        return $this->grupo;
    }

    public function setGrupo(?string $grupo): static {
        $this->grupo = $grupo;
        return $this;
    }

    public function getRuta(): ?string {
        return $this->ruta;
    }

    public function setRuta(?string $ruta): static {
        $this->ruta = $ruta;
        return $this;
    }

    /**
     * @return Collection<int, Role>
     */
    public function getRoles(): Collection {
        return $this->roles;
    }

    public function addRole(Role $role): static {
        if (!$this->roles->contains($role)) {
            $this->roles->add($role);
        }
        return $this;
    }

    public function removeRole(Role $role): static {
        $this->roles->removeElement($role);
        return $this;
    }

    /**
     * @return Collection<int, Permiso>
     */
    public function getPermisos(): Collection {
        return $this->permisos;
    }

    public function addPermiso(Permiso $permiso): static {
        if (!$this->permisos->contains($permiso)) {
            $this->permisos->add($permiso);
        }
        return $this;
    }

    public function removePermiso(Permiso $permiso): static {
        $this->permisos->removeElement($permiso);
        return $this;
    }
}
