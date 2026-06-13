<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ApiToken;
use App\Entity\Usuario;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $this->createUser(
            manager: $manager,
            username: 'superadmin',
            nombre: 'Super',
            apellido: 'Admin',
            email: 'super@fdn.local',
            plainPassword: 'super123',
            roleRefs: ['ROLE_SUPER_ADMIN'],
            directPermisoRefs: [],
            directActionRefs: [],
            deniedActionRefs: [],
        );

        $this->createUser(
            manager: $manager,
            username: 'admin',
            nombre: 'Admin',
            apellido: 'Usuario',
            email: 'admin@fdn.local',
            plainPassword: 'admin123',
            roleRefs: ['ROLE_ADMIN'],
            directPermisoRefs: ['Gestion Acciones'],
            directActionRefs: [],
            deniedActionRefs: [],
        );

        $this->createUser(
            manager: $manager,
            username: 'operador',
            nombre: 'Operador',
            apellido: 'Perez',
            email: 'operador@fdn.local',
            plainPassword: 'operador123',
            roleRefs: ['ROLE_OPERADOR'],
            directPermisoRefs: [],
            directActionRefs: ['usuario.ver'],
            deniedActionRefs: [],
        );

        $this->createUser(
            manager: $manager,
            username: 'consulta',
            nombre: 'Consulta',
            apellido: 'Garcia',
            email: 'consulta@fdn.local',
            plainPassword: 'consulta123',
            roleRefs: ['ROLE_CONSULTA'],
            directPermisoRefs: ['Gestion Boletos'],
            directActionRefs: [],
            deniedActionRefs: ['boleto.anular'],
        );

        $manager->flush();
    }

    private function createUser(
        ObjectManager $manager,
        string $username,
        string $nombre,
        string $apellido,
        string $email,
        string $plainPassword,
        array $roleRefs,
        array $directPermisoRefs,
        array $directActionRefs,
        array $deniedActionRefs,
    ): Usuario {
        $user = new Usuario();
        $user->setUsername($username);
        $user->setNombre($nombre);
        $user->setApellido($apellido);
        $user->setEmail($email);

        $hashed = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashed);

        foreach ($roleRefs as $roleName) {
            $role = $this->getReference(RoleFixtures::REF_PREFIX . $roleName, \App\Entity\Role::class);
            $user->addUserRole($role);
        }

        foreach ($directPermisoRefs as $permisoNombre) {
            $permiso = $this->getReference(PermisoFixtures::REF_PREFIX . $permisoNombre, \App\Entity\Permiso::class);
            $user->addPermiso($permiso);
        }

        foreach ($directActionRefs as $actionCode) {
            $action = $this->getReference(ActionFixtures::REF_PREFIX . $actionCode, \App\Entity\Action::class);
            $user->addDirectAction($action);
        }

        foreach ($deniedActionRefs as $actionCode) {
            $action = $this->getReference(ActionFixtures::REF_PREFIX . $actionCode, \App\Entity\Action::class);
            $user->addDeniedAction($action);
        }

        $token = new ApiToken();
        $token->setUsuario($user);
        $token->setActivo(true);
        $user->addApiToken($token);
        $manager->persist($token);

        $manager->persist($user);

        return $user;
    }

    public function getDependencies(): array
    {
        return [
            RoleFixtures::class,
        ];
    }
}
