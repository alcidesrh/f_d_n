<?php

declare(strict_types=1);

namespace App\Entity;

use App\Acceso\Recurso;
use ApiPlatform\Metadata\ApiProperty;
use App\Attribute\ApiResourceNoPagination;
use App\Entity\Base\Base;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Menú de navegación: una `Taxonomy` de `MenuItem` (su árbol, con una o más
 * raíces) más los roles de su audiencia.
 *
 * Visibilidad (`App\Navigation\UserMenus`, ADR-049): el usuario ve el menú
 * si está colocado en un área (`MenuPlacement`), si su audiencia lo incluye
 * (`App\Navigation\Audiencia`: sin roles, todos) y si le queda algún ítem
 * que puede abrir (`App\Navigation\Pantallas`).
 */
#[ORM\Entity]
#[ApiResourceNoPagination]
#[Recurso(nombre: "Menús", area: "Configuración", lecturaLibre: true)]
class Menu extends Base
{
    public const SUBJECT = 'MenuItem';

    #[Assert\NotBlank]
    #[ORM\Column(length: 255)]
    private ?string $nombre = null;

    /**
     * Audiencia: a quién se le muestra (curaduría, no seguridad); vacía, a todos.
     *
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
