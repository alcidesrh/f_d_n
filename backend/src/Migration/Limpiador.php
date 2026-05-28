<?php

namespace App\Migration;

use Doctrine\DBAL\Connection;

class Limpiador
{
    private array $tables = [
        'boleto_asiento',
        'boleto',
        'salida',
        'tarifa_trayecto',
        'tarifa',
        'trayecto_trayecto',
        'trayecto',
        'asiento',
        'bus',
        'cliente',
        'enclave',
        'empresa',
    ];

    private array $sequences = [
        'boleto_id_seq',
        'salida_id_seq',
        'tarifa_id_seq',
        'trayecto_id_seq',
        'asiento_id_seq',
        'bus_id_seq',
        'cliente_id_seq',
        'enclave_id_seq',
        'empresa_id_seq',
    ];

    public function __construct(
        private Connection $newConn,
    ) {
    }

    public function limpiar(): void
    {
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
