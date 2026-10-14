<?php

namespace App\Migration;

use Doctrine\DBAL\Connection;

class Limpiador
{
    /**
     * Tablas que no dependen del legado: las crea o edita la gente en el
     * sistema nuevo (menús, rutas del frontend, íconos, IAM, configuración de
     * entidades y de la página) y limpiarConservando() no las toca. El IAM se
     * conserva porque menús y rutas lo referencian; MigradorIAM es idempotente
     * por nombre/código y solo agrega lo que falte.
     */
    public const CONSERVABLES = [
        "icon",
        "vue_route",
        "vue_route_role",
        "menu",
        "menu_item",
        "menu_placement",
        "menu_role",
        "taxonomy",
        "taxonomy_node",
        "entity_configuration",
        "collection_field_config",
        "form_field_config",
        "role",
        "role_role",
        "role_permiso",
        "role_action",
        "permiso",
        "permiso_permiso",
        "permiso_action",
        "action",
        "status",
        "configuracion_pagina",
    ];

    private array $tables = [
        "boleto_asiento",
        "boleto",
        "salida_parada",
        "salida",
        "parada_tiempo",
        "parada",
        "ruta",
        "trayecto",
        "asiento",
        "bus",
        "cliente",
        "enclave",
        "empresa",
        "user_direct_action",
        "user_denied_action",
        "user_role",
        "role_action",
        "role_permiso",
        "permiso_action",
        "role_role",
        "permiso_permiso",
        "action",
        "permiso",
        "role",
        "usuario",
        "api_token",
    ];

    private array $sequences = [
        "boleto_id_seq",
        "salida_id_seq",
        "tarifa_id_seq",
        "trayecto_id_seq",
        "asiento_id_seq",
        "bus_id_seq",
        "cliente_id_seq",
        "enclave_id_seq",
        "empresa_id_seq",
        "usuario_id_seq",
        "api_token_id_seq",
        "action_id_seq",
        "permiso_id_seq",
        "role_id_seq",
    ];

    public function __construct(private Connection $newConn)
    {
        $schemaManager = $this->newConn->createSchemaManager();
        $this->tables = array_map(
            fn($t) => $t->toString(),
            $schemaManager->introspectTableNames(),
        );
        $this->sequences = array_map(
            fn($s) => $s->toString(),
            $schemaManager->introspectSequences(),
        );
    }

    public function limpiar(): void
    {
        $this->newConn->executeStatement(
            "SET session_replication_role = replica",
        );
        foreach ($this->tables as $table) {
            $this->newConn->executeStatement("TRUNCATE TABLE {$table} CASCADE");
        }

        foreach ($this->sequences as $seq) {
            $this->newConn->executeStatement(
                "ALTER SEQUENCE {$seq} RESTART WITH 1",
            );
        }

        $this->newConn->executeStatement(
            "SET session_replication_role = origin",
        );
    }

    /**
     * Vacía las tablas que vienen del legado y conserva las CONSERVABLES.
     *
     * Una conservada que apunta a una vaciada (p. ej. `configuracion_pagina`
     * → `usuario`) pierde la referencia si la columna admite NULL; si no la
     * admite, la fila depende de un dato del legado y la tabla se vacía
     * también (p. ej. `vue_route_usuario_permitidos`).
     *
     * Postgres no deja truncar una tabla referida por otra que no se trunca,
     * aunque la referencia esté en NULL: las referidas por conservadas (y lo
     * que ellas referencian) se vacían con DELETE y se reinicia su secuencia;
     * el resto, con un solo TRUNCATE sin CASCADE (si quedara una dependencia
     * sin resolver, falla en vez de borrar de más). Todo en una transacción.
     *
     * @return array{conservadas: list<string>, vaciadas: list<string>}
     */
    public function limpiarConservando(): array
    {
        $tablas = $this->newConn->fetchFirstColumn(
            "SELECT tablename FROM pg_tables WHERE schemaname = current_schema()",
        );
        $conservar = array_fill_keys(
            array_intersect(self::CONSERVABLES, $tablas),
            true,
        );
        $fks = $this->newConn->fetchAllAssociative(
            "SELECT t.relname AS tabla, a.attname AS columna,
                    r.relname AS referida, a.attnotnull AS obligatoria
               FROM pg_constraint c
               JOIN pg_class t ON t.oid = c.conrelid
               JOIN pg_class r ON r.oid = c.confrelid
               JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = ANY (c.conkey)
              WHERE c.contype = 'f' AND c.connamespace = current_schema()::regnamespace",
        );

        // Una conservada con referencia obligatoria a una vaciada se vacía también.
        do {
            $cambio = false;
            foreach ($fks as $fk) {
                if (
                    isset($conservar[$fk["tabla"]]) &&
                    !isset($conservar[$fk["referida"]]) &&
                    $fk["obligatoria"]
                ) {
                    unset($conservar[$fk["tabla"]]);
                    $cambio = true;
                }
            }
        } while ($cambio);

        // Referidas por una conservada, y lo que ellas referencian: DELETE.
        $porDelete = [];
        foreach ($fks as $fk) {
            if (isset($conservar[$fk["tabla"]]) && !isset($conservar[$fk["referida"]])) {
                $porDelete[$fk["referida"]] = true;
            }
        }
        do {
            $cambio = false;
            foreach ($fks as $fk) {
                if (
                    isset($porDelete[$fk["tabla"]]) &&
                    !isset($conservar[$fk["referida"]]) &&
                    !isset($porDelete[$fk["referida"]])
                ) {
                    $porDelete[$fk["referida"]] = true;
                    $cambio = true;
                }
            }
        } while ($cambio);

        $vaciar = array_values(
            array_filter($tablas, fn($t) => !isset($conservar[$t])),
        );
        $porTruncate = array_values(
            array_filter($vaciar, fn($t) => !isset($porDelete[$t])),
        );
        $q = fn(string $id) => $this->newConn->quoteSingleIdentifier($id);

        $this->newConn->transactional(function () use ($fks, $conservar, $porDelete, $porTruncate, $q) {
            foreach ($fks as $fk) {
                if (isset($conservar[$fk["tabla"]]) && !isset($conservar[$fk["referida"]])) {
                    $this->newConn->executeStatement(sprintf(
                        "UPDATE %s SET %s = NULL WHERE %2\$s IS NOT NULL",
                        $q($fk["tabla"]),
                        $q($fk["columna"]),
                    ));
                }
            }
            if ($porTruncate) {
                $this->newConn->executeStatement(
                    "TRUNCATE TABLE " . implode(", ", array_map($q, $porTruncate)) . " RESTART IDENTITY",
                );
            }
            if ($porDelete) {
                // Lo que las referenciaba ya está vacío o en NULL: sin chequeos de FK.
                $this->newConn->executeStatement("SET LOCAL session_replication_role = replica");
                foreach (array_keys($porDelete) as $tabla) {
                    $this->newConn->executeStatement("DELETE FROM " . $q($tabla));
                    $secuencias = $this->newConn->fetchFirstColumn(
                        "SELECT s.oid::regclass::text FROM pg_class s
                           JOIN pg_depend d ON d.objid = s.oid AND d.classid = 'pg_class'::regclass
                                           AND d.refclassid = 'pg_class'::regclass
                          WHERE s.relkind = 'S' AND d.refobjid = :t::regclass",
                        ["t" => $q($tabla)],
                    );
                    foreach ($secuencias as $seq) {
                        $this->newConn->executeStatement("ALTER SEQUENCE {$seq} RESTART WITH 1");
                    }
                }
            }
        });

        $conservadas = array_keys($conservar);
        sort($conservadas);

        return ["conservadas" => $conservadas, "vaciadas" => $vaciar];
    }
}
