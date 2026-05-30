<?php

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Attribute\ApiResourcePaginationPage;
use App\Attribute\FormMetadataAttribute;
use App\Entity\Base\Base;
use App\Entity\Base\Constants\RolesTrait;
use App\Repository\RoleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ApiResourceNoPagination]

class Role extends Base {

    use RolesTrait;

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    /**
     * @var Collection<int, self>
     */
    #[FormMetadataAttribute(merge: ['options' => '$parents'])]
    #[ORM\ManyToMany(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    private ?Collection $parents;

    /**
     * @var Collection<int, self>
     */
    #[FormMetadataAttribute(merge: ['options' => '$children'])]
    #[ORM\ManyToMany(targetEntity: self::class, mappedBy: 'parents')]
    private ?Collection $children;

    /**
     * @var Collection<int, Permiso>
     */
    #[FormMetadataAttribute(merge: ['options' => '$permisos'])]
    #[ORM\ManyToMany(targetEntity: Permiso::class, inversedBy: 'roles')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    private ?Collection $permisos;

    /**
     * @var Collection<int, Action>
     */
    #[ORM\ManyToMany(targetEntity: Action::class, inversedBy: 'roles')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    private Collection $actions;

    public function __construct() {
        $this->parents = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->permisos = new ArrayCollection();
        $this->actions = new ArrayCollection();
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

    /**
     * @return Collection<int, self>
     */
    public function getParents(): Collection {
        return $this->parents;
    }

    public function addParent(self $parent): static {
        if (!$this->parents->contains($parent)) {
            $this->parents->add($parent);
        }

        return $this;
    }

    public function removeParent(self $parent): static {
        $this->parents->removeElement($parent);

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection {
        return $this->children;
    }

    public function addChild(self $child): static {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->addParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): static {
        if ($this->children->removeElement($child)) {
            $child->removeParent($this);
        }

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
            $permiso->addRole($this);
        }

        return $this;
    }

    public function removePermiso(Permiso $permiso): static {
        if ($this->permisos->removeElement($permiso)) {
            $permiso->removeRole($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Action>
     */
    public function getActions(): Collection {
        return $this->actions;
    }

    public function addAction(Action $action): static {
        if (!$this->actions->contains($action)) {
            $this->actions->add($action);
            $action->addRole($this);
        }

        return $this;
    }

    public function removeAction(Action $action): static {
        if ($this->actions->removeElement($action)) {
            $action->removeRole($this);
        }

        return $this;
    }

    public function __toString(): string {
        return $this->getNombre() ?? '';
    }
}
