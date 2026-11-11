<?php

declare(strict_types=1);

namespace App\Sincronizacion\Legado\Importacion;

use App\Acceso\Catalogo\Catalogo;
use App\Acceso\Legado\ConsolidacionRoles;
use App\Acceso\Legado\EquivalenciasLegado;
use Doctrine\DBAL\Connection;

/**
 * Accesos de un usuario del legado en el modelo nuevo (ADR-039, ADR-052): sus
 * roles de `custom_user.roles` (array PHP serializado con los nombres:
 * `a:2:{i:0;s:10:"ROLE_ADMIN";…}`) se consolidan en puestos
 * (`ConsolidacionRoles`): los de oficina sobre sus empresas
 * (`custom_user_empresa`) y los de estación sobre esas empresas y sus
 * estaciones (`custom_user.estacion_id` y `custom_user_estacion`); y si sigue
 * activo. `ROLE_USER` es implícito; `ROLE_NO_VENDER_VOUCHER` quita la
 * cortesía. Con un rol total (sistema o administración), solo ese. A un
 * usuario de agencia el alcance se lo da su agencia.
 *
 * Se aplica al crear el usuario (paso «usuario» o bajo demanda al migrar una
 * salida, `DependenciasLegado::usuario`) y en el paso «iam». Rehace solo las
 * asignaciones que puso la migración: las otorgadas a mano se respetan. El
 * puesto que aún no exista se crea con sus capacidades (`ConsolidacionRoles::PUESTOS`).
 */
final class RolesUsuarioLegado
{
    public const MOTIVO = 'Migrado del legado (ADR-039)';
    /** Las que creó la migración de esquema desde `user_role`. */
    public const MOTIVO_MODELO_ANTERIOR = 'Migrado del modelo anterior (ADR-039)';

    public const SQL_EMPRESAS = 'SELECT empresa_id FROM custom_user_empresa WHERE user_id = :id';
    public const SQL_ESTACIONES = 'SELECT estacion_id FROM custom_user_estacion WHERE user_id = :id';

    /** @var array<string, int> */
    private array $roles = [];

    public function __construct(
        private readonly Connection $db,
        private readonly Catalogo $catalogo,
    ) {}

    /**
     * @param list<int|string> $empresas   ids del legado (`custom_user_empresa`)
     * @param list<int|string> $estaciones ids del legado (principal y adicionales)
     * @param bool|null        $activo     lo deja así si se indica
     *
     * @return int asignaciones creadas
     */
    public function asignar(int $usuarioId, mixed $rolesLegado, array $empresas = [], array $estaciones = [], ?bool $activo = null): int
    {
        if ($activo !== null) {
            $this->db->executeStatement('UPDATE usuario SET activo = :activo WHERE id = :id', ['activo' => $activo ? 'true' : 'false', 'id' => $usuarioId]);
        }
        $agencia = $this->db->fetchOne('SELECT agencia_id FROM usuario WHERE id = :id', ['id' => $usuarioId]);
        $empresas = $this->existentes('empresa', $empresas);
        $estaciones = $this->existentes('enclave', $estaciones);

        $this->db->executeStatement(
            'DELETE FROM asignacion WHERE usuario_id = :id AND otorgada_por_id IS NULL AND motivo IN (:motivos)',
            ['id' => $usuarioId, 'motivos' => [self::MOTIVO, self::MOTIVO_MODELO_ANTERIOR]],
            ['motivos' => \Doctrine\DBAL\ArrayParameterType::STRING],
        );

        $nombres = array_values(array_diff(array_unique(self::nombres($rolesLegado)), [EquivalenciasLegado::BASE]));
        $totales = array_values(array_filter($nombres, EquivalenciasLegado::total(...)));
        $deAgencia = $agencia !== null && $agencia !== false;

        // [puestos, empresas, estaciones] por alcance; null = todas.
        $asignaciones = [];
        if ($totales !== []) {
            $asignaciones[] = [ConsolidacionRoles::consolidar($totales, $nombres)['puestos'], null, null];
        } elseif ($deAgencia) {
            $asignaciones[] = [ConsolidacionRoles::consolidar($nombres, $nombres, null)['puestos'], null, null];
        } else {
            $oficina = ConsolidacionRoles::consolidar($nombres, $nombres, false)['puestos'];
            $yaTiene = array_merge([], ...array_map(static fn(string $p) => ConsolidacionRoles::capacidades($p) ?? [], $oficina));
            $asignaciones[] = [$oficina, $empresas, null];
            $asignaciones[] = [ConsolidacionRoles::consolidar($nombres, $nombres, true, $yaTiene)['puestos'], $empresas, $estaciones];
        }

        $count = 0;
        foreach ($asignaciones as [$puestos, $empresasAsignacion, $estacionesAsignacion]) {
            foreach ($puestos as $puesto) {
                $count += $this->db->executeStatement(
                    'INSERT INTO asignacion (usuario_id, rol_id, empresas, estaciones, motivo, creada, modificada)
                     VALUES (:usuario, :rol, CAST(:empresas AS json), CAST(:estaciones AS json), :motivo, NOW(), NOW())',
                    [
                        'usuario' => $usuarioId,
                        'rol' => $this->rol($puesto),
                        'empresas' => $empresasAsignacion === null ? null : json_encode($empresasAsignacion, JSON_THROW_ON_ERROR),
                        'estaciones' => $estacionesAsignacion === null ? null : json_encode($estacionesAsignacion, JSON_THROW_ON_ERROR),
                        'motivo' => self::MOTIVO,
                    ],
                );
            }
        }

        return $count;
    }

    /** Un usuario del legado sigue activo si está habilitado, sin bloquear y sin vencer. */
    public static function activo(array $fila): bool
    {
        $si = static fn(mixed $v) => $v === true || $v === 1 || $v === '1';

        return $si($fila['enabled'] ?? true) && !$si($fila['locked'] ?? false) && !$si($fila['expired'] ?? false);
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

    /** El puesto por nombre; si no existe, se crea con sus capacidades (`ConsolidacionRoles::PUESTOS`). */
    public function rol(string $nombre, ?string $descripcion = null): int
    {
        if (isset($this->roles[$nombre])) {
            return $this->roles[$nombre];
        }
        $id = $this->db->fetchOne('SELECT id FROM role WHERE nombre = :nombre', ['nombre' => $nombre]);
        if ($id === false) {
            $id = $this->db->fetchOne(
                'INSERT INTO role (nombre, descripcion, capacidades, limites, sistema) VALUES (:nombre, :descripcion, CAST(:capacidades AS json), CAST(:limites AS json), :sistema) RETURNING id',
                [
                    'nombre' => $nombre,
                    'descripcion' => mb_substr(ConsolidacionRoles::PUESTOS[$nombre][2] ?? ($descripcion !== null && $descripcion !== $nombre ? $descripcion : ''), 0, 255) ?: null,
                    'capacidades' => json_encode(ConsolidacionRoles::capacidades($nombre) ?? EquivalenciasLegado::capacidades($nombre, $this->catalogo->codigos()) ?? [], JSON_THROW_ON_ERROR),
                    'limites' => '{}',
                    'sistema' => $nombre === EquivalenciasLegado::SISTEMA ? 'true' : 'false',
                ],
            );
        }

        return $this->roles[$nombre] = (int) $id;
    }

    /**
     * @param list<int|string> $ids
     *
     * @return list<int> los que existen en la base nueva (los ids del legado se conservan)
     */
    private function existentes(string $tabla, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id) => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $existen = array_map('intval', $this->db->fetchFirstColumn(
            "SELECT id FROM $tabla WHERE id IN (:ids)",
            ['ids' => $ids],
            ['ids' => \Doctrine\DBAL\ArrayParameterType::INTEGER],
        ));
        sort($existen);

        return $existen;
    }
}
