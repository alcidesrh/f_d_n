<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use App\Repository\MenuRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_NOMBRE', fields: ['nombre'])]
#[ApiResourceNoPagination]
class Menu extends Base {

    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    #[ORM\Column(length: 255, nullable: true)]
    public ?string $label = null;

    #[ORM\ManyToOne(targetEntity: VueRoute::class)]
    private ?VueRoute $referenciaVueRoute = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(nullable: true)]
    private ?int $sort = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\ManyToMany(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinTable(name: 'menu_menu')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'child_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Collection $parents;

    /**
     * @var Collection<int, self>
     */
    #[ORM\ManyToMany(targetEntity: self::class, mappedBy: 'parents')]
    private ?Collection $children;

    /**
     * @var Collection<int, Role>
     */
    #[ORM\ManyToMany(targetEntity: Role::class)]
    private Collection $allowRoles;

    /**
     * @var Collection<int, Permiso>
     */
    #[ORM\ManyToMany(targetEntity: Permiso::class)]
    private Collection $allowPermiso;

    /**
     * @var Collection<int, MenuLayoutAssignment>
     */
    #[ORM\OneToMany(targetEntity: MenuLayoutAssignment::class, mappedBy: 'menu', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $layoutAssignments;

    public function __construct() {
        $this->parents = new ArrayCollection();
        $this->children = new ArrayCollection();
        $this->allowRoles = new ArrayCollection();
        $this->allowPermiso = new ArrayCollection();
        $this->layoutAssignments = new ArrayCollection();
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

    public function getLabel(): ?string {
        return $this->label ?? $this->nombre;
    }

    public function setLabel(?string $label): static {
        $this->label = $label;

        return $this;
    }

    public function getReferenciaVueRoute(): ?VueRoute {
        return $this->referenciaVueRoute;
    }

    public function setReferenciaVueRoute(?VueRoute $referenciaVueRoute): static {
        $this->referenciaVueRoute = $referenciaVueRoute;

        return $this;
    }

    public function getIcon(): ?string {
        return $this->icon;
    }

    public function setIcon(?string $icon): static {
        $this->icon = $icon;

        return $this;
    }

    public function getSort(): ?int {
        return $this->sort;
    }

    public function setSort(?int $sort): static {
        $this->sort = $sort;

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

    public function __toString(): string {
        return $this->getNombre() ?? '';
    }

    /**
     * @return Collection<int, Role>
     */
    public function getAllowRoles(): Collection
    {
        return $this->allowRoles;
    }

    public function addAllowRole(Role $allowRole): static
    {
        if (!$this->allowRoles->contains($allowRole)) {
            $this->allowRoles->add($allowRole);
        }

        return $this;
    }

    public function removeAllowRole(Role $allowRole): static
    {
        $this->allowRoles->removeElement($allowRole);

        return $this;
    }

    /**
     * @return Collection<int, Permiso>
     */
    public function getAllowPermiso(): Collection
    {
        return $this->allowPermiso;
    }

    public function addAllowPermiso(Permiso $allowPermiso): static
    {
        if (!$this->allowPermiso->contains($allowPermiso)) {
            $this->allowPermiso->add($allowPermiso);
        }

        return $this;
    }

    public function removeAllowPermiso(Permiso $allowPermiso): static
    {
        $this->allowPermiso->removeElement($allowPermiso);

        return $this;
    }

    /**
     * @return Collection<int, MenuLayoutAssignment>
     */
    public function getLayoutAssignments(): Collection
    {
        return $this->layoutAssignments;
    }

    public function addLayoutAssignment(MenuLayoutAssignment $layoutAssignment): static
    {
        if (!$this->layoutAssignments->contains($layoutAssignment)) {
            $this->layoutAssignments->add($layoutAssignment);
            $layoutAssignment->setMenu($this);
        }

        return $this;
    }

    public function removeLayoutAssignment(MenuLayoutAssignment $layoutAssignment): static
    {
        if ($this->layoutAssignments->removeElement($layoutAssignment)) {
            if ($layoutAssignment->getMenu() === $this) {
                $layoutAssignment->setMenu(null);
            }
        }

        return $this;
    }
}
