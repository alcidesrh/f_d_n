<?php

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use App\Entity\Usuario;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Usuario>
 *
 * @implements PasswordUpgraderInterface<Usuario>
 *
 * @method Usuario|null find($id, $lockMode = null, $lockVersion = null)
 * @method Usuario|null findOneBy(array $criteria, array $orderBy = null)
 * @method Usuario[]    findAll()
 * @method Usuario[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UsuarioRepository extends ServiceEntityRepository implements PasswordUpgraderInterface {
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Usuario::class);
    }

    /**
     * Used to upgrade (rehash) the Usuario's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $Usuario, string $newHashedPassword): void {
        if (!$Usuario instanceof Usuario) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $Usuario::class));
        }

        $Usuario->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($Usuario);
        $this->getEntityManager()->flush();
    }

    /**
     * Anota el inicio de sesión. Un UPDATE directo: no pasa por el ciclo de vida
     * de Doctrine, así que no mueve `updatedAt` ni genera una fila de bitácora
     * por cada login.
     */
    public function registrarInicioSesion(Usuario $usuario, \DateTimeImmutable $momento): void {
        $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE usuario SET last_login = ? WHERE id = ?',
            [$momento, $usuario->getId()],
            [Types::DATETIME_IMMUTABLE, ParameterType::INTEGER],
        );
        $usuario->setLastLogin($momento);
    }
}
