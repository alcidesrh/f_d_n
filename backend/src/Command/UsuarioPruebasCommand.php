<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Role;
use App\Entity\Usuario;
use App\Repository\UsuarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Cuenta para probar contra el stack de desarrollo (Playwright, agentes,
 * `curl`): crea el usuario (ROLE_SUPER_ADMIN) o le pone una contraseña nueva
 * al azar y escribe en la salida `E2E_ADMIN_USER=…` y `E2E_ADMIN_PASSWORD=…`.
 * `make e2e-usuario` guarda eso en `.env.e2e.local` (raíz, fuera de git).
 *
 * Solo en desarrollo y pruebas: en producción no hace nada. No es la cuenta
 * `admin` de respaldo (`App\Migration\AsegurarUsuarioAdmin`).
 */
#[AsCommand(
    name: 'app:usuario:pruebas',
    description: 'Crea o reinicia la cuenta de pruebas (solo desarrollo) e imprime sus credenciales para .env.e2e.local.',
)]
final class UsuarioPruebasCommand extends Command
{
    public const USERNAME = 'pruebas';
    private const ROLE = 'ROLE_SUPER_ADMIN';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UsuarioRepository $usuarios,
        private readonly UserPasswordHasherInterface $passwordHasher,
        #[Autowire('%kernel.environment%')]
        private readonly string $entorno,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('username', null, InputOption::VALUE_REQUIRED, 'Nombre de usuario', self::USERNAME);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->entorno === 'prod') {
            $output->getErrorOutput()->writeln('<error>La cuenta de pruebas no existe en producción.</error>');

            return Command::FAILURE;
        }

        $username = (string) $input->getOption('username');
        $usuario = $this->usuarios->findOneBy(['username' => $username]);
        if ($usuario === null) {
            $usuario = (new Usuario())
                ->setUsername($username)
                ->setNombre('Pruebas')
                ->setApellido('Automáticas')
                ->setEmail("$username@fdn.local");
            $this->entityManager->persist($usuario);
        }

        $role = $this->entityManager->getRepository(Role::class)->findOneBy(['nombre' => self::ROLE]);
        if ($role === null) {
            $role = (new Role())->setNombre(self::ROLE);
            $this->entityManager->persist($role);
        }
        if (!$usuario->getUserRoles()->contains($role)) {
            $usuario->addUserRole($role);
        }

        $password = bin2hex(random_bytes(16));
        $usuario->setPassword($this->passwordHasher->hashPassword($usuario, $password));
        $this->entityManager->flush();

        $output->writeln("E2E_ADMIN_USER=$username");
        $output->writeln("E2E_ADMIN_PASSWORD=$password");

        return Command::SUCCESS;
    }
}
