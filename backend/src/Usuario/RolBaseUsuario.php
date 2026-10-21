<?php

declare(strict_types=1);

namespace App\Usuario;

use App\Entity\Role;
use App\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

/**
 * Todo usuario nuevo recibe `ROLE_USER` (el rol base de los menús y de
 * `/administracion`), venga del CRUD, de la consola o de los fixtures. Los
 * existentes lo recibieron en `Version20261021180000`.
 */
#[AsEntityListener(event: Events::prePersist, entity: Usuario::class)]
final class RolBaseUsuario
{
    public function prePersist(Usuario $usuario, PrePersistEventArgs $args): void
    {
        if (in_array(Role::user, $usuario->getRoles(), true)) {
            return;
        }

        $rol = $args->getObjectManager()->getRepository(Role::class)->findOneBy(['nombre' => Role::user]);
        if ($rol !== null) {
            $usuario->addUserRole($rol);
        }
    }
}
