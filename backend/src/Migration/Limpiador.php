<?php

namespace App\Migration;

use Doctrine\DBAL\Connection;

class Limpiador {
    private array $tables = [
        'boleto_asiento',
        'boleto',
        'servicio',
        'tarifa_trayecto',
        'tarifa',
        'trayecto_trayecto',
        'trayecto',
        'asiento',
        'bus',
        'cliente',
        'enclave',
        'empresa',
        'user_direct_action',
        'user_denied_action',
        'user_role',
        'role_action',
        'role_permiso',
        'permiso_action',
        'role_role',
        'permiso_permiso',
        'action',
        'permiso',
        'role',
        'usuario',
        'api_token',
    ];

    private array $sequences = [
        'boleto_id_seq',
        'servicio_id_seq',
        'tarifa_id_seq',
        'trayecto_id_seq',
        'asiento_id_seq',
        'bus_id_seq',
        'cliente_id_seq',
        'enclave_id_seq',
        'empresa_id_seq',
        'usuario_id_seq',
        'api_token_id_seq',
        'action_id_seq',
        'permiso_id_seq',
        'role_id_seq',
    ];

    public function __construct(
        private Connection $newConn,
    ) {
        $schemaManager = $this->newConn->createSchemaManager();
        $this->tables = array_map(fn($t) => $t->toString(), $schemaManager->introspectTableNames());
        $this->sequences = array_map(fn($s) => $s->toString(), $schemaManager->introspectSequences());
    }

    public function limpiar(): void {
        $this->newConn->executeStatement('SET session_replication_role = replica');
        foreach ($this->tables as $table) {
            $this->newConn->executeStatement("TRUNCATE TABLE {$table} CASCADE");
        }

        foreach ($this->sequences as $seq) {
            $this->newConn->executeStatement("ALTER SEQUENCE {$seq} RESTART WITH 1");
        }

        $this->newConn->executeStatement('SET session_replication_role = origin');
    }
}
