<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Menú de navegación: una `Taxonomy` de `MenuItem` (su árbol, con una o más
 * raíces) más los roles que pueden verlo.
 *
 * Visibilidad (`App\Navigation\MenuVisibility`): el usuario ve el menú si
 * alguno de sus roles está en `roles` o es ascendiente (`Role.parents`) de
 * alguno de ellos, y el menú está colocado en un área (`MenuPlacement`).
 */
#[ORM\Entity]
#[ApiResourceNoPagination]
class Menu extends Base
{
    public const SUBJECT = 'MenuItem';

    #[Assert\NotBlank]
    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    /**
     * @var Collection<int, Role>
     */
    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinTable(name: 'menu_role')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    private Collection $roles;

    /** El árbol se edita por `/api/menus/{id}/tree`, no por el CRUD. */
    #[ApiProperty(readable: false, writable: false)]
    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private Taxonomy $taxonomy;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
        $this->taxonomy = new Taxonomy('Menú', self::SUBJECT);
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function setNombre(string $nombre): static
    {
        $this->nombre = $nombre;
        $this->taxonomy->setNombre('Menú: ' . $nombre);

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

    public function getTaxonomy(): Taxonomy
    {
        return $this->taxonomy;
    }
}
