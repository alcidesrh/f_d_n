<?php

declare(strict_types=1);

namespace App\Usuario;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Borrado de usuarios (`/api/usuarios/eliminacion`). Los registros que apuntan
 * a `usuario` se leen de las claves foráneas de la base, así que una tabla
 * nueva aparece sola. Cada tabla con registros de esos usuarios:
 *  - de accesos (tokens, permisos, rutas del usuario): se borra sola con el usuario (cascada);
 *  - con la columna anulable: se puede desvincular (`NULL`) o eliminar sus filas;
 *  - con la columna obligatoria: solo se pueden eliminar sus filas;
 *  - fiscal (`boleto_venta`): solo se desvincula, nunca se borra.
 */
final class EliminacionUsuarios
{
    public const DESVINCULAR = 'desvincular';
    public const ELIMINAR = 'eliminar';

    /** Tablas de accesos del usuario: la cascada de la base las limpia sin preguntar. */
    private const ACCESOS = [
        'api_token',
        'chat_miembro',
        'user_role',
        'user_direct_action',
        'user_denied_action',
        'usuario_permiso',
        'vue_route_usuario_denegados',
        'vue_route_usuario_permitidos',
    ];

    /** Registros con valor fiscal: se desvinculan, no se borran. */
    private const SOLO_DESVINCULAR = ['boleto_venta'];

    /** Registros de muestra por tabla en el análisis. */
    private const MUESTRA = 50;

    public function __construct(
        private readonly Connection $db,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Lo que depende de los usuarios: solo las tablas con registros.
     *
     * @param list<int> $ids
     * @return list<array{tabla: string, columna: string, entidad: ?string, anulable: bool, total: int, acciones: list<string>, registros: list<array{id: int, etiqueta: string}>}>
     */
    public function analizar(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $grupos = [];
        foreach ($this->referencias() as $ref) {
            if (in_array($ref['tabla'], self::ACCESOS, true)) {
                continue;
            }
            $tabla = $this->db->quoteIdentifier($ref['tabla']);
            $columna = $this->db->quoteIdentifier($ref['columna']);
            $total = (int) $this->db->fetchOne(
                "SELECT COUNT(*) FROM $tabla WHERE $columna IN (?)",
                [$ids],
                [ArrayParameterType::INTEGER],
            );
            if ($total === 0) {
                continue;
            }

            $grupos[] = [
                'tabla' => $ref['tabla'],
                'columna' => $ref['columna'],
                'entidad' => $this->entidadDeTabla($ref['tabla']),
                'anulable' => $ref['anulable'],
                'total' => $total,
                'acciones' => $this->accionesPermitidas($ref),
                'registros' => $this->muestra($ref['tabla'], $ref['columna'], $ids),
            ];
        }

        return $grupos;
    }

    /**
     * Desvincula o elimina lo que depende de los usuarios y borra los usuarios,
     * todo en una transacción.
     *
     * @param list<int> $ids
     * @param array<string, string> $decisiones tabla => `desvincular` | `eliminar`
     * @return array{usuarios: int, tablas: array<string, array{accion: string, filas: int}>}
     */
    public function eliminar(array $ids, array $decisiones): array
    {
        $this->db->beginTransaction();
        try {
            $tablas = [];
            $faltan = [];
            foreach ($this->analizar($ids) as $grupo) {
                $accion = $decisiones[$grupo['tabla']] ?? null;
                if ($accion === null) {
                    $faltan[] = $grupo['entidad'] ?? $grupo['tabla'];
                    continue;
                }
                if (!in_array($accion, $grupo['acciones'], true)) {
                    throw new EliminacionInvalida(sprintf('No se puede "%s" en %s.', $accion, $grupo['entidad'] ?? $grupo['tabla']));
                }

                $tabla = $this->db->quoteIdentifier($grupo['tabla']);
                $columna = $this->db->quoteIdentifier($grupo['columna']);
                $filas = $accion === self::DESVINCULAR
                    ? $this->db->executeStatement("UPDATE $tabla SET $columna = NULL WHERE $columna IN (?)", [$ids], [ArrayParameterType::INTEGER])
                    : $this->db->executeStatement("DELETE FROM $tabla WHERE $columna IN (?)", [$ids], [ArrayParameterType::INTEGER]);
                $tablas[$grupo['tabla']] = ['accion' => $accion, 'filas' => $filas];
            }

            if ($faltan !== []) {
                throw new EliminacionInvalida('Indique qué hacer con: ' . implode(', ', $faltan) . '.');
            }

            $usuarios = $this->db->executeStatement('DELETE FROM usuario WHERE id IN (?)', [$ids], [ArrayParameterType::INTEGER]);
            $this->db->commit();
        } catch (ForeignKeyConstraintViolationException $e) {
            $this->db->rollBack();
            throw new EliminacionInvalida('Hay otros registros que dependen de lo que se quiere eliminar; desvincúlelos o revise la lista.', previous: $e);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->em->clear();

        return ['usuarios' => $usuarios, 'tablas' => $tablas];
    }

    /**
     * Claves foráneas que apuntan a `usuario`, con si la columna admite NULL.
     *
     * @return list<array{tabla: string, columna: string, anulable: bool}>
     */
    private function referencias(): array
    {
        $filas = $this->db->fetchAllAssociative(<<<'SQL'
            SELECT tc.table_name AS tabla, kcu.column_name AS columna, c.is_nullable AS anulable
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
              ON kcu.constraint_name = tc.constraint_name AND kcu.table_schema = tc.table_schema
            JOIN information_schema.constraint_column_usage ccu
              ON ccu.constraint_name = tc.constraint_name AND ccu.table_schema = tc.table_schema
            JOIN information_schema.columns c
              ON c.table_schema = tc.table_schema AND c.table_name = tc.table_name AND c.column_name = kcu.column_name
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND ccu.table_name = 'usuario'
              AND tc.table_schema = current_schema()
            ORDER BY 1, 2
            SQL);

        return array_map(
            static fn (array $f): array => [
                'tabla' => (string) $f['tabla'],
                'columna' => (string) $f['columna'],
                'anulable' => $f['anulable'] === 'YES',
            ],
            $filas,
        );
    }

    /** @param array{tabla: string, anulable: bool} $ref @return list<string> */
    private function accionesPermitidas(array $ref): array
    {
        $acciones = [];
        if ($ref['anulable']) {
            $acciones[] = self::DESVINCULAR;
        }
        if (!in_array($ref['tabla'], self::SOLO_DESVINCULAR, true)) {
            $acciones[] = self::ELIMINAR;
        }

        return $acciones;
    }

    /** @param list<int> $ids @return list<array{id: int, etiqueta: string}> */
    private function muestra(string $tabla, string $columna, array $ids): array
    {
        $clase = $this->claseDeTabla($tabla);
        $campo = $clase !== null ? $this->campoDeRelacion($clase, $columna) : null;

        if ($clase !== null && $campo !== null) {
            $entidades = $this->em->createQueryBuilder()
                ->select('e')
                ->from($clase, 'e')
                ->where("IDENTITY(e.$campo) IN (:ids)")
                ->setParameter('ids', $ids)
                ->setMaxResults(self::MUESTRA)
                ->getQuery()
                ->getResult();

            return array_map(function (object $entidad): array {
                $id = (int) current($this->em->getUnitOfWork()->getEntityIdentifier($entidad));

                return [
                    'id' => $id,
                    'etiqueta' => method_exists($entidad, 'getLabel') ? (string) $entidad->getLabel() : '#' . $id,
                ];
            }, $entidades);
        }

        $filas = $this->db->fetchFirstColumn(
            sprintf('SELECT id FROM %s WHERE %s IN (?) LIMIT %d', $this->db->quoteIdentifier($tabla), $this->db->quoteIdentifier($columna), self::MUESTRA),
            [$ids],
            [ArrayParameterType::INTEGER],
        );

        return array_map(static fn ($id): array => ['id' => (int) $id, 'etiqueta' => '#' . $id], $filas);
    }

    /** Nombre corto de la entidad que mapea la tabla, si la hay. */
    private function entidadDeTabla(string $tabla): ?string
    {
        $clase = $this->claseDeTabla($tabla);

        return $clase === null ? null : substr($clase, (int) strrpos($clase, '\\') + 1);
    }

    /** @return class-string|null */
    private function claseDeTabla(string $tabla): ?string
    {
        foreach ($this->em->getMetadataFactory()->getAllMetadata() as $meta) {
            if ($meta->getTableName() === $tabla) {
                return $meta->getName();
            }
        }

        return null;
    }

    /** Nombre de la asociación ManyToOne/OneToOne que usa esa columna. */
    private function campoDeRelacion(string $clase, string $columna): ?string
    {
        foreach ($this->em->getClassMetadata($clase)->getAssociationMappings() as $campo => $mapeo) {
            if (($mapeo['joinColumns'][0]['name'] ?? null) === $columna) {
                return $campo;
            }
        }

        return null;
    }
}
