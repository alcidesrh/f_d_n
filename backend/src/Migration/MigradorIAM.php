<?php

namespace App\Migration;

use App\Acceso\Legado\EquivalenciasLegado;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Accesos del legado en el modelo nuevo (ADR-039), paso «iam» de la
 * migración y `app:acceso:alcance-legado`. Idempotente.
 *
 * 1. Roles: uno por `custom_rol` (con su descripción); al crearse lleva las
 *    capacidades de `EquivalenciasLegado` —los que ya existen conservan las
 *    suyas, que pueden haberse editado en el sistema nuevo—. ROLE_SUPER_ADMIN
 *    es el de sistema.
 * 2. Usuarios ya migrados: si siguen activos (`enabled`, sin `locked` ni
 *    `expired`) y una asignación por rol con el alcance del legado
 *    (`custom_user_empresa`, `custom_user.estacion_id`, `custom_user_estacion`).
 *    Rehace solo las asignaciones de la migración (`RolesUsuarioLegado`).
 */
class MigradorIAM {
    public function __construct(
        private Connection $newConn,
        #[Target('oldPdo')] private \PDO $oldPdo,
        private RolesUsuarioLegado $rolesUsuario,
    ) {
        $this->oldPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->oldPdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    /** @return array{roles: int, usuarios: int, asignaciones: int, desactivados: int} */
    public function migrar(?OutputInterface $output = null): array {
        $contadores = ['roles' => 0, 'usuarios' => 0, 'asignaciones' => 0, 'desactivados' => 0];

        $this->newConn->beginTransaction();
        try {
            if ($output) $output->write('<info>Roles...</info>');
            foreach ($this->fetchOld('SELECT nombre, descripcion FROM custom_rol') as $row) {
                $existia = $this->newConn->fetchOne('SELECT 1 FROM role WHERE nombre = :nombre', ['nombre' => $row['nombre']]) !== false;
                $this->rolesUsuario->rol((string) $row['nombre'], $row['descripcion'] !== null ? (string) $row['descripcion'] : null);
                $contadores['roles'] += $existia ? 0 : 1;
            }
            $this->newConn->executeStatement('UPDATE role SET sistema = true WHERE nombre = :nombre', ['nombre' => EquivalenciasLegado::SISTEMA]);
            if ($output) $output->writeln(" <info>{$contadores['roles']} nuevos</info>");

            if ($output) $output->write('<info>Usuarios y asignaciones...</info>');
            $empresas = $this->agrupar('SELECT user_id, empresa_id AS id FROM custom_user_empresa');
            $estaciones = $this->agrupar('SELECT user_id, estacion_id AS id FROM custom_user_estacion');
            foreach ($this->fetchOld('SELECT id, roles, estacion_id, enabled, locked, expired FROM custom_user') as $row) {
                $id = (int) $row['id'];
                if ($this->newConn->fetchOne('SELECT 1 FROM usuario WHERE id = :id', ['id' => $id]) === false) {
                    continue;
                }
                $activo = RolesUsuarioLegado::activo($row);
                $contadores['usuarios']++;
                $contadores['desactivados'] += $activo ? 0 : 1;
                $contadores['asignaciones'] += $this->rolesUsuario->asignar(
                    $id,
                    $row['roles'] ?? null,
                    $empresas[$id] ?? [],
                    [...(empty($row['estacion_id']) ? [] : [(int) $row['estacion_id']]), ...($estaciones[$id] ?? [])],
                    $activo,
                );
            }
            if ($output) $output->writeln(" <info>{$contadores['usuarios']} usuarios, {$contadores['asignaciones']} asignaciones, {$contadores['desactivados']} desactivados</info>");

            $this->newConn->commit();
        } catch (\Throwable $e) {
            $this->newConn->rollBack();
            throw new \RuntimeException("Error migrando IAM: {$e->getMessage()}", 0, $e);
        }

        return $contadores;
    }

    /** @return array<int, list<int>> por usuario */
    private function agrupar(string $sql): array {
        $grupos = [];
        foreach ($this->fetchOld($sql) as $row) {
            $grupos[(int) $row['user_id']][] = (int) $row['id'];
        }

        return $grupos;
    }

    private function sanitizeUtf8(array $row): array {
        $clean = [];
        foreach ($row as $k => $v) {
            $clean[$k] = is_string($v) ? mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1') : $v;
        }
        return $clean;
    }

    private function fetchOld(string $sql, array $params = []): \Generator {
        $stmt = $this->oldPdo->prepare($sql);
        $stmt->execute($params);
        while ($row = $stmt->fetch()) {
            yield $this->sanitizeUtf8($row);
        }
    }
}
