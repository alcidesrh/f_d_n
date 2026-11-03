<?php

declare(strict_types=1);

namespace App\Entity\Base\Traits;

use App\Entity\Role;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * A quién se le muestra el registro (ADR-049): los roles de su audiencia;
 * sin ninguno, a todos. Es curaduría: lo que decide si algo se ve es si el
 * usuario puede abrir su pantalla. La regla vive en `App\Navigation\Audiencia`.
 * La clase que lo usa inicializa la colección en su constructor.
 */
trait AudienciaTrait
{
    /** @var Collection<int, Role> */
    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    #[ORM\InverseJoinColumn(onDelete: "CASCADE")]
    private Collection $roles;

    /** @return Collection<int, Role> */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    /** @param iterable<Role> $roles */
    public function mostrarA(iterable $roles): static
    {
        $this->roles->clear();
        foreach ($roles as $rol) {
            $this->roles->add($rol);
        }

        return $this;
    }
}
