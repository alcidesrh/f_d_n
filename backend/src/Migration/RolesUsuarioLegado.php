<?php

declare(strict_types=1);

namespace App\Migration;

use Doctrine\DBAL\Connection;

/**
 * Roles de un usuario del legado (`custom_user.roles`, array PHP serializado
 * con los nombres: `a:2:{i:0;s:10:"ROLE_ADMIN";…}`) más `ROLE_USER`.
 *
 * Se aplica al crear el usuario, sea en el paso «usuario» o bajo demanda al
 * migrar una salida (`DependenciasLegado::usuario`), porque el paso «iam»
 * puede haber corrido antes de que existieran los usuarios. El rol que aún
 * no exista se crea por nombre (el paso «iam» le suma luego sus permisos).
 */
final class RolesUsuarioLegado
{
    public function __construct(private readonly Connection $db) {}

    /** @return int asignaciones nuevas */
    public function asignar(int $usuarioId, mixed $rolesLegado): int
    {
        $nombres = self::nombres($rolesLegado);
        $nombres[] = 'ROLE_USER';

        $count = 0;
        foreach (array_unique($nombres) as $nombre) {
            $count += $this->db->executeStatement(
                'INSERT INTO user_role (user_id, role_id) VALUES (:uid, :rid) ON CONFLICT DO NOTHING',
                ['uid' => $usuarioId, 'rid' => $this->rol($nombre)],
            );
        }

        return $count;
    }

    /** @return list<string> */
    public static function nombres(mixed $rolesLegado): array
    {
        if (!is_string($rolesLegado) || $rolesLegado === '') {
            return [];
        }
        $roles = @unserialize($rolesLegado, ['allowed_classes' => false]);
        if (!is_array($roles)) {
            $roles = json_decode($rolesLegado, true);
        }
        if (!is_array($roles)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn($r) => is_string($r) ? trim($r) : '', $roles),
            static fn(string $r) => str_starts_with($r, 'ROLE_'),
        ));
    }

    private function rol(string $nombre): int
    {
        $id = $this->db->fetchOne('SELECT id FROM role WHERE nombre = :nombre', ['nombre' => $nombre]);
        if ($id === false) {
            $id = $this->db->fetchOne('INSERT INTO role (nombre) VALUES (:nombre) RETURNING id', ['nombre' => $nombre]);
        }

        return (int) $id;
    }
}
