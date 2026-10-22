<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ApiToken;
use App\Entity\Asignacion;
use App\Entity\Role;
use App\Entity\Usuario;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Usuarios de ejemplo con asignaciones (ADR-039). */
class UserFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $this->crear($manager, 'superadmin', 'Super', 'Admin', 'super123', [[RoleFixtures::SISTEMA, null, null]]);
        // Vende en dos empresas desde una estación; supervisa solo una.
        $this->crear($manager, 'vendedor', 'Vendedor', 'Pérez', 'vendedor123', [[RoleFixtures::VENDEDOR, [1, 2], [1]]]);
        $this->crear($manager, 'supervisor', 'Supervisor', 'García', 'supervisor123', [[RoleFixtures::SUPERVISOR, [1], [1]]]);

        $manager->flush();
    }

    /**
     * @param list<array{0: string, 1: list<int>|null, 2: list<int>|null}> $asignaciones rol, empresas, estaciones
     */
    private function crear(ObjectManager $manager, string $username, string $nombre, string $apellido, string $password, array $asignaciones): void
    {
        $user = (new Usuario())
            ->setUsername($username)
            ->setNombre($nombre)
            ->setApellido($apellido)
            ->setEmail("$username@fdn.local");
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $manager->persist($user);

        foreach ($asignaciones as [$rol, $empresas, $estaciones]) {
            $manager->persist((new Asignacion($user, $this->getReference(RoleFixtures::REF_PREFIX . $rol, Role::class)))
                ->setEmpresas($empresas)
                ->setEstaciones($estaciones));
        }

        $token = (new ApiToken())->setUsuario($user)->setActivo(true);
        $user->addApiToken($token);
        $manager->persist($token);
    }

    public function getDependencies(): array
    {
        return [RoleFixtures::class];
    }
}
