<?php

declare(strict_types=1);

namespace App\Migration;

use App\Entity\Usuario;
use Doctrine\DBAL\Connection;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Garantiza una cuenta de administrador de respaldo en la tabla `usuario`.
 *
 * Regla: si no existe un usuario con username `admin`, lo crea con el rol
 * ROLE_SUPER_ADMIN y password `'    '` (cuatro espacios en blanco). Idempotente:
 * si `admin` ya existe (p. ej. migrado desde el legado) no hace nada.
 *
 * El id se fija en -1 (negativo, reservado) a propósito: la migración del
 * legado conserva los ids originales (positivos) en `usuario.id`
 * (MigradorEstaticos::existe comprueba `WHERE id = :lid`), de modo que un id
 * negativo nunca colisiona con un usuario legacy ni con la secuencia.
 *
 * Se invoca tras vaciar la BD (Reseteador::soft/hard) para que siempre quede
 * una cuenta con la que entrar. El ApiToken se crea solo al hacer login
 * (SecurityController::login), por eso aquí no se siembra ninguno.
 */
final class AsegurarUsuarioAdmin
{
    public const USERNAME = 'admin';
    public const PLAIN_PASSWORD = '    '; // cuatro espacios en blanco
    public const NOMBRE = 'Admin';
    public const APELLIDO = 'Sistema';
    public const EMAIL = 'admin@fdn.local';
    public const ROLE = 'ROLE_SUPER_ADMIN';
    public const ID = -1; // id negativo reservado: no colisiona con ids del legado

    public function __construct(
        private readonly Connection $conn,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function asegurar(): void
    {
        $existe = $this->conn->fetchOne(
            'SELECT 1 FROM usuario WHERE username = :username',
            ['username' => self::USERNAME],
        );
        if ($existe !== false) {
            return; // ya existe un usuario `admin`
        }

        $roleId = $this->conn->fetchOne(
            'SELECT id FROM role WHERE nombre = :nombre',
            ['nombre' => self::ROLE],
        );
        if ($roleId === false) {
            $roleId = $this->conn->fetchOne(
                'INSERT INTO role (nombre) VALUES (:nombre) RETURNING id',
                ['nombre' => self::ROLE],
            );
        }

        $hash = $this->passwordHasher->hashPassword(
            new Usuario(),
            self::PLAIN_PASSWORD,
        );

        $this->conn->executeStatement(
            'INSERT INTO usuario (id, username, password, nombre, apellido, email, created_at, updated_at) '
            . 'VALUES (:id, :username, :password, :nombre, :apellido, :email, NOW(), NOW())',
            [
                'id' => self::ID,
                'username' => self::USERNAME,
                'password' => $hash,
                'nombre' => self::NOMBRE,
                'apellido' => self::APELLIDO,
                'email' => self::EMAIL,
            ],
        );

        $this->conn->executeStatement(
            'INSERT INTO user_role (user_id, role_id) VALUES (:userId, :roleId) ON CONFLICT DO NOTHING',
            ['userId' => self::ID, 'roleId' => (int) $roleId],
        );
    }
}
