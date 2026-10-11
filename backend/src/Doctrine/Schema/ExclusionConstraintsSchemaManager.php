<?php

declare(strict_types=1);

namespace App\Doctrine\Schema;

use Doctrine\DBAL\Schema\PostgreSQLSchemaManager;

/**
 * Las restricciones de exclusión de PostgreSQL (`EXCLUDE USING gist`) no
 * tienen mapeo en Doctrine y su índice aparecería como sobrante en cada
 * `schema:update`/`migrations:diff`. Se ignoran los índices `ex_*`: se crean
 * y se mantienen solo en migraciones (p. ej. `ex_boleto_asiento_tramo`, ADR-030).
 */
final class ExclusionConstraintsSchemaManager extends PostgreSQLSchemaManager
{
    public const PREFIJO = "ex_";

    protected function _getPortableTableIndexesList(array $rows, string $tableName): array
    {
        return parent::_getPortableTableIndexesList(
            array_values(array_filter($rows, static fn(array $r) => !str_starts_with(trim((string) $r["relname"], '"'), self::PREFIJO))),
            $tableName,
        );
    }
}
