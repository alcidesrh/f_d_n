<?php

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Attribute\CollectionMetadataAttribute;
use App\Attribute\FormMetadataAttribute;
use App\Entity\Base\NombreNotaStatusBase;
use App\Repository\PermisoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PermisoRepository::class)]
#[ApiResourceNoPagination]
#[FormMetadataAttribute(order: ['nombre', 'roles', 'parents', 'children', 'status', 'nota'])]

#[CollectionMetadataAttribute(
    class: 'col-wraper',
    props: [
        ['name' => 'id', 'class' => ' col-small'],
        ['name' => 'nombre', 'class' => 'col-wraper'],
        ['name' => 'parents', 'label' => 'Padre'],
        ['name' => 'children', 'label' => 'Hijos'],
        ['name' => 'roles']
    ]
)]
class Permiso extends NombreNotaStatusBase {


    // use StatusTrait;

    /**
     * @var Collection<int, Role>
     */
    #[ORM\ManyToMany(targetEntity: Role::class,  mappedBy: 'permisos')]
    private ?Collection $roles;

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

    public function __construct() {
        $this->roles = new ArrayCollection();
        $this->parents = new ArrayCollection();
        $this->children = new ArrayCollection();
    }

    public function getId(): ?int {
        return $this->id;
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
}
