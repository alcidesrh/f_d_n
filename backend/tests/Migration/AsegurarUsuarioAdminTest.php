<?php

declare(strict_types=1);

namespace App\Tests\Migration;

use App\Entity\Usuario;
use App\Migration\AsegurarUsuarioAdmin;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AsegurarUsuarioAdminTest extends TestCase
{
    /**
     * Conexión simulada (stub): fetchOne devuelve valores en orden y
     * executeStatement registra las consultas en $ejecutados.
     *
     * @param array<int, mixed> $fetchOne
     * @param array<int, array{0: string, 1: array<string, mixed>}> $ejecutados
     */
    private function conexion(array $fetchOne, array &$ejecutados): Connection
    {
        $conn = $this->createStub(Connection::class);

        $pendientes = $fetchOne;
        $conn
            ->method("fetchOne")
            ->willReturnCallback(
                function () use (&$pendientes): mixed {
                    return array_shift($pendientes);
                },
            );

        $conn
            ->method("executeStatement")
            ->willReturnCallback(
                function (
                    string $sql,
                    array $params = [],
                ) use (&$ejecutados): int {
                    $ejecutados[] = [$sql, $params];

                    return 1;
                },
            );

        return $conn;
    }

    public function testCreaAdminCuandoLaTablaEstaVacia(): void
    {
        $ejecutados = [];
        // sin admin, sin role ROLE_SUPER_ADMIN, insert rol → id 42
        $conn = $this->conexion([false, false, 42], $ejecutados);

        $hasher = $this->createStub(
            UserPasswordHasherInterface::class,
        );
        $hasher
            ->method("hashPassword")
            ->willReturnCallback(
                function (
                    object $user,
                    string $plainPassword,
                ): string {
                    self::assertInstanceOf(Usuario::class, $user);
                    self::assertSame("    ", $plainPassword);

                    return "hash-de-prueba";
                },
            );

        (new AsegurarUsuarioAdmin($conn, $hasher))->asegurar();

        self::assertCount(2, $ejecutados);

        [$usuarioSql, $usuario] = $ejecutados[0];
        self::assertStringContainsString("INSERT INTO usuario", $usuarioSql);
        self::assertSame(-1, $usuario["id"]);
        self::assertSame("admin", $usuario["username"]);
        self::assertSame("hash-de-prueba", $usuario["password"]);
        self::assertSame("Admin", $usuario["nombre"]);
        self::assertSame("Sistema", $usuario["apellido"]);
        self::assertSame("admin@fdn.local", $usuario["email"]);

        [$linkSql, $link] = $ejecutados[1];
        self::assertStringContainsString("user_role", $linkSql);
        self::assertSame(-1, $link["userId"]);
        self::assertSame(42, $link["roleId"]);
    }

    public function testNoHaceNadaCuandoAdminYaExiste(): void
    {
        $ejecutados = [];
        $conn = $this->conexion([1], $ejecutados);

        $hasher = $this->createStub(
            UserPasswordHasherInterface::class,
        );

        (new AsegurarUsuarioAdmin($conn, $hasher))->asegurar();

        self::assertCount(0, $ejecutados);
    }

    public function testReutilizaElRolExistente(): void
    {
        $ejecutados = [];
        // sin admin, pero el rol ROLE_SUPER_ADMIN ya existe con id 7
        $conn = $this->conexion([false, 7], $ejecutados);

        $hasher = $this->createStub(
            UserPasswordHasherInterface::class,
        );
        $hasher->method("hashPassword")->willReturn("hash");

        (new AsegurarUsuarioAdmin($conn, $hasher))->asegurar();

        self::assertCount(2, $ejecutados);
        self::assertSame(7, $ejecutados[1][1]["roleId"]);
    }
}
