<?php

namespace App\Migration;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Adapts the legacy role/user model to the new Flat Permission Set architecture.
 *
 * Legacy: custom_rol (name-only), custom_user.roles (JSON array of role names).
 * New: Role (hierarchical, with Permiso/Action links), Usuario.
 *
 * Steps:
 * 1. Create base Action codes for all business operations.
 * 2. Create base Permiso groups, link to Actions.
 * 3. Create base Roles (ADMIN, OPERADOR, CONSULTA), link to Permisos/Actions.
 * 4. Migrate legacy roles → new Roles.
 * 5. Migrate legacy user → new Usuario (if not already migrated).
 * 6. Assign legacy user-role mappings.
 *
 * Note: Role, Permiso, and Action entities use StatusTrait (status_id FK)
 * instead of an 'activo' boolean column.
 */
class MigradorIAM {
    private const BASE_ACTIONS = [
        ['boleto.crear', 'Boleto', 'create', 'Boletos'],
        ['boleto.ver', 'Boleto', 'read', 'Boletos'],
        ['boleto.editar', 'Boleto', 'update', 'Boletos'],
        ['boleto.anular', 'Boleto', 'delete', 'Boletos'],
        ['boleto.reasignar', 'Boleto', 'reassign', 'Boletos'],
        ['servicio.crear', 'Servicio', 'create', 'Servicios'],
        ['servicio.ver', 'Servicio', 'read', 'Servicios'],
        ['servicio.editar', 'Servicio', 'update', 'Servicios'],
        ['servicio.cancelar', 'Servicio', 'cancel', 'Servicios'],
        ['ruta.ver', 'Ruta', 'read', 'Rutas'],
        ['ruta.editar', 'Ruta', 'update', 'Rutas'],
        ['empresa.ver', 'Empresa', 'read', 'Empresas'],
        ['empresa.editar', 'Empresa', 'update', 'Empresas'],
        ['usuario.ver', 'Usuario', 'read', 'Usuarios'],
        ['usuario.editar', 'Usuario', 'update', 'Usuarios'],
        ['cliente.ver', 'Cliente', 'read', 'Clientes'],
        ['cliente.crear', 'Cliente', 'create', 'Clientes'],
        ['tarifa.ver', 'Tarifa', 'read', 'Tarifas'],
        ['tarifa.editar', 'Tarifa', 'update', 'Tarifas'],
        ['asiento.ver', 'Asiento', 'read', 'Asientos'],
        ['reporte.ventas', 'Reporte', 'read', 'Reportes'],
        ['config.ver', 'Config', 'read', 'Configuración'],
        ['config.editar', 'Config', 'update', 'Configuración'],
        ['iam.ver', 'IAM', 'read', 'IAM'],
        ['iam.editar', 'IAM', 'update', 'IAM'],
    ];

    private const BASE_PERMISOS = [
        'Gestion Acciones' => [
            'boleto.crear',
            'boleto.ver',
            'boleto.editar',
            'boleto.anular',
            'boleto.reasignar',
            'servicio.crear',
            'servicio.ver',
            'servicio.editar',
            'servicio.cancelar',
            'ruta.ver',
            'empresa.ver',
            'cliente.ver',
            'cliente.crear',
            'tarifa.ver'
        ],
        'Gestion Usuarios' => ['usuario.ver', 'usuario.editar', 'iam.ver', 'iam.editar'],
        'Gestion Reportes' => ['reporte.ventas', 'config.ver'],
        'Gestion Config' => ['config.ver', 'config.editar', 'empresa.editar', 'ruta.editar', 'tarifa.editar'],
        'Gestion Asientos' => ['asiento.ver'],
    ];

    public function __construct(
        private Connection $newConn,
        #[Target('doctrine.dbal.systemfdn_connection')] private Connection $oldConn,
    ) {
        // $this->oldPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        // $this->oldPdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    public function migrar(?OutputInterface $output = null): array {
        $contadores = [
            'actions' => 0,
            'permisos' => 0,
            'roles' => 0,
            'rol_legacy' => 0,
            'user_role' => 0,
        ];

        $this->newConn->beginTransaction();
        try {
            if ($output) $output->write('<info>Actions...</info>');
            $contadores['actions'] = $this->crearActionsBase();
            if ($output) $output->writeln(" <info>{$contadores['actions']}</info>");

            if ($output) $output->write('<info>Permisos...</info>');
            $contadores['permisos'] = $this->crearPermisosBase();
            if ($output) $output->writeln(" <info>{$contadores['permisos']}</info>");

            if ($output) $output->write('<info>Roles base...</info>');
            $contadores['roles'] = $this->crearRolesBase();
            if ($output) $output->writeln(" <info>{$contadores['roles']}</info>");

            if ($output) $output->write('<info>Roles legacy...</info>');
            $contadores['rol_legacy'] = $this->migrarRolesLegacy();
            if ($output) $output->writeln(" <info>{$contadores['rol_legacy']}</info>");

            if ($output) $output->write('<info>User-Role...</info>');
            $contadores['user_role'] = $this->asignarRolesUsuarios();
            if ($output) $output->writeln(" <info>{$contadores['user_role']}</info>");

            $this->newConn->commit();
        } catch (\Throwable $e) {
            $this->newConn->rollBack();
            throw new \RuntimeException("Error migrando IAM: {$e->getMessage()}", 0, $e);
        }

        return $contadores;
    }

    private function crearActionsBase(): int {
        $count = 0;

        foreach (self::BASE_ACTIONS as [$codigo, $recurso, $operacion, $grupo]) {
            $exists = $this->newConn->fetchOne(
                'SELECT 1 FROM action WHERE codigo = :codigo',
                ['codigo' => $codigo]
            );
            if ($exists) {
                continue;
            }

            $this->newConn->executeStatement(
                'INSERT INTO action (codigo, recurso, operacion, grupo, nombre) VALUES (:codigo, :recurso, :operacion, :grupo, :nombre)',
                [
                    'codigo' => $codigo,
                    'recurso' => $recurso,
                    'operacion' => $operacion,
                    'grupo' => $grupo,
                    'nombre' => $codigo,
                ]
            );
            $count++;
        }

        return $count;
    }

    private function crearPermisosBase(): int {
        $count = 0;

        foreach (self::BASE_PERMISOS as $nombre => $actionCodigos) {
            $exists = $this->newConn->fetchOne(
                'SELECT id FROM permiso WHERE nombre = :nombre',
                ['nombre' => $nombre]
            );
            if ($exists) {
                $permisoId = (int) $exists;
            } else {
                $permisoId = (int) $this->newConn->fetchOne(
                    'INSERT INTO permiso (nombre) VALUES (:nombre) RETURNING id',
                    ['nombre' => $nombre]
                );
                $count++;
            }

            foreach ($actionCodigos as $codigo) {
                $actionId = $this->newConn->fetchOne(
                    'SELECT id FROM action WHERE codigo = :codigo',
                    ['codigo' => $codigo]
                );
                if (!$actionId) {
                    continue;
                }

                $this->newConn->executeStatement(
                    'INSERT INTO permiso_action (permiso_id, action_id) VALUES (:pid, :aid) ON CONFLICT DO NOTHING',
                    ['pid' => $permisoId, 'aid' => (int) $actionId]
                );
            }
        }

        return $count;
    }

    private function crearRolesBase(): int {
        $count = 0;

        $roles = [
            'ROLE_ADMIN' => ['Gestion Acciones', 'Gestion Usuarios', 'Gestion Reportes', 'Gestion Config', 'Gestion Asientos'],
            'ROLE_OPERADOR' => ['Gestion Acciones'],
            'ROLE_CONSULTA' => [],
        ];

        $actionIds = [];
        foreach (self::BASE_ACTIONS as [$codigo]) {
            $aid = $this->newConn->fetchOne('SELECT id FROM action WHERE codigo = :codigo', ['codigo' => $codigo]);
            if ($aid) {
                $actionIds[$codigo] = (int) $aid;
            }
        }

        foreach ($roles as $nombre => $permisoNombres) {
            $exists = $this->newConn->fetchOne(
                'SELECT 1 FROM role WHERE nombre = :nombre',
                ['nombre' => $nombre]
            );
            if ($exists) {
                continue;
            }

            $roleId = (int) $this->newConn->fetchOne(
                'INSERT INTO role (nombre) VALUES (:nombre) RETURNING id',
                ['nombre' => $nombre]
            );
            $count++;

            foreach ($permisoNombres as $permisoNombre) {
                $permisoId = $this->newConn->fetchOne(
                    'SELECT id FROM permiso WHERE nombre = :nombre',
                    ['nombre' => $permisoNombre]
                );
                if ($permisoId) {
                    $this->newConn->executeStatement(
                        'INSERT INTO role_permiso (role_id, permiso_id) VALUES (:rid, :pid) ON CONFLICT DO NOTHING',
                        ['rid' => $roleId, 'pid' => (int) $permisoId]
                    );
                }
            }

            if ($nombre === 'ROLE_ADMIN') {
                foreach ($actionIds as $codigo => $aid) {
                    $this->newConn->executeStatement(
                        'INSERT INTO role_action (role_id, action_id) VALUES (:rid, :aid) ON CONFLICT DO NOTHING',
                        ['rid' => $roleId, 'aid' => $aid]
                    );
                }
            }
        }

        return $count;
    }

    private function migrarRolesLegacy(): int {
        $rows = $this->fetchOld('SELECT * FROM custom_rol');
        $count = 0;

        foreach ($rows as $row) {
            $nombre = $row['nombre'];
            $exists = $this->newConn->fetchOne(
                'SELECT 1 FROM role WHERE nombre = :nombre',
                ['nombre' => $nombre]
            );
            if ($exists) {
                continue;
            }

            $this->newConn->executeStatement(
                'INSERT INTO role (nombre) VALUES (:nombre)',
                ['nombre' => $nombre]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Assigns user-role relationships from the legacy custom_user.roles JSON column.
     *
     * The roles column contains a JSON array of role names (e.g. ["ROLE_ADMIN"]).
     * Each name matches the role.nombre in the local database.
     */
    private function asignarRolesUsuarios(): int {
        $rows = $this->fetchOld(
            'SELECT id, roles FROM custom_user WHERE roles IS NOT NULL AND roles != \'[]\''
        );

        $count = 0;
        foreach ($rows as $row) {
            $userId = $this->newConn->fetchOne(
                'SELECT id FROM usuario WHERE id = :id',
                ['id' => $row['id']]
            );
            if (!$userId) {
                continue;
            }

            $roleNames = unserialize($row['roles']);
            if (!is_array($roleNames)) {
                continue;
            }

            foreach ($roleNames as $roleName) {
                $roleId = $this->newConn->fetchOne(
                    'SELECT id FROM role WHERE nombre = :nombre',
                    ['nombre' => $roleName]
                );
                if (!$roleId) {
                    continue;
                }

                $this->newConn->executeStatement(
                    'INSERT INTO user_role (user_id, role_id) VALUES (:uid, :rid) ON CONFLICT DO NOTHING',
                    ['uid' => (int) $userId, 'rid' => (int) $roleId]
                );
                $count++;
            }
        }

        return $count;
    }

    private function fetchOld(string $sql, array $params = []): \Generator {
        $stmt = $this->oldConn->executeQuery($sql, $params)->fetchAllAssociative();
        // $stmt->execute($params);
        // while ($row = $stmt->fetch()) {
        //     yield $row;
        // }
        foreach ($$stmt as $key => $value) {
            yield $row;
        }
    }
}
