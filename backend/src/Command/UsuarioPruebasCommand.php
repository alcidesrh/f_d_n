<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Asignacion;
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
 * `curl`): crea el usuario (rol de sistema sobre todo, ADR-039) o le pone una contraseña nueva
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

        $role = $this->entityManager->getRepository(Role::class)->findOneBy(['sistema' => true], ['id' => 'ASC']);
        if ($role === null) {
            $role = (new Role())->setNombre(self::ROLE)->setDescripcion('Administración total del sistema')->setSistema(true);
            $this->entityManager->persist($role);
        }
        $tiene = $usuario->getAsignaciones()->exists(static fn($i, Asignacion $a) => $a->getRol() === $role && $a->getEmpresas() === null && $a->getEstaciones() === null);
        if (!$tiene) {
            $this->entityManager->persist((new Asignacion($usuario, $role))->setMotivo('Cuenta de pruebas'));
        }
        $usuario->setActivo(true);

        $password = bin2hex(random_bytes(16));
        $usuario->setPassword($this->passwordHasher->hashPassword($usuario, $password));
        $this->entityManager->flush();

        $output->writeln("E2E_ADMIN_USER=$username");
        $output->writeln("E2E_ADMIN_PASSWORD=$password");

        return Command::SUCCESS;
    }
}
