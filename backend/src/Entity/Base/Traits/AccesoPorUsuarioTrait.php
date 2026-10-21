<?php

declare(strict_types=1);

namespace App\Entity\Base\Traits;

use App\Entity\Role;
use App\Entity\Usuario;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Quién puede ver un registro: los usuarios con alguno de `roles` (o un
 * ascendiente, como en los menús) y los `usuarios` nombrados. Sin roles ni
 * usuarios, cualquiera con sesión. La regla vive en `App\Administracion\Acceso`.
 * La clase que lo usa inicializa ambas colecciones en su constructor.
 */
trait AccesoPorUsuarioTrait
{
    /** @var Collection<int, Role> */
    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    #[ORM\InverseJoinColumn(onDelete: "CASCADE")]
    private Collection $roles;

    /** @var Collection<int, Usuario> */
    #[ORM\ManyToMany(targetEntity: Usuario::class)]
    #[ORM\JoinColumn(onDelete: "CASCADE")]
    #[ORM\InverseJoinColumn(onDelete: "CASCADE")]
    private Collection $usuarios;

    /** @return Collection<int, Role> */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    /** @return Collection<int, Usuario> */
    public function getUsuarios(): Collection
    {
        return $this->usuarios;
    }

    /**
     * @param iterable<Role> $roles
     * @param iterable<Usuario> $usuarios
     */
    public function restringirA(iterable $roles, iterable $usuarios): static
    {
        $this->roles->clear();
        foreach ($roles as $rol) {
            $this->roles->add($rol);
        }
        $this->usuarios->clear();
        foreach ($usuarios as $usuario) {
            $this->usuarios->add($usuario);
        }

        return $this;
    }
}
