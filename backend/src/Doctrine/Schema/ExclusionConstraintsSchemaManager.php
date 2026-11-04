<?php

declare(strict_types=1);

namespace App\Doctrine\Schema;

use Doctrine\DBAL\Schema\PostgreSQLSchemaManager;
use Doctrine\DBAL\Schema\Table;

/**
 * Las restricciones de exclusión de PostgreSQL (`EXCLUDE USING gist`) no
 * tienen mapeo en Doctrine y su índice aparecería como sobrante en cada
 * `schema:update`/`migrations:diff`. Se ignoran los índices `ex_*`: se crean
 * y se mantienen solo en migraciones (p. ej. `ex_boleto_asiento_tramo`, ADR-030).
 *
 * Además, la tabla leída de la base tiene solo los índices que existen: DBAL
 * le suma uno "implícito" a cada clave foránea sin un índice de exactamente
 * sus columnas, aunque no exista. Con `IndicesCubiertos` (que quita del
 * esquema generado los que otro índice ya cubre) eso daría un `DROP INDEX`
 * de un índice inexistente en cada diff.
 */
final class ExclusionConstraintsSchemaManager extends PostgreSQLSchemaManager
{
    public const PREFIJO = "ex_";

    /** @var array<string, list<string>> índices que existen, por tabla */
    private array $fisicos = [];

    protected function _getPortableTableIndexesList(array $rows, string $tableName): array
    {
        $indices = parent::_getPortableTableIndexesList(
            array_values(array_filter($rows, static fn(array $r) => !str_starts_with(trim((string) $r["relname"], '"'), self::PREFIJO))),
            $tableName,
        );
        $this->fisicos[strtolower($tableName)] = array_map(strtolower(...), array_map(strval(...), array_keys($indices)));

        return $indices;
    }

    public function listTables(): array
    {
        return array_map($this->soloFisicos(...), parent::listTables());
    }

    public function introspectTable(string $name): Table
    {
        return $this->soloFisicos(parent::introspectTable($name));
    }

    private function soloFisicos(Table $tabla): Table
    {
        $fisicos = $this->fisicos[strtolower($tabla->getName())] ?? null;
        if ($fisicos === null) {
            return $tabla;
        }
        foreach ($tabla->getIndexes() as $nombre => $indice) {
            if (!$indice->isPrimary() && !\in_array(strtolower((string) $nombre), $fisicos, true)) {
                $tabla->dropIndex((string) $nombre);
            }
        }

        return $tabla;
    }
}
