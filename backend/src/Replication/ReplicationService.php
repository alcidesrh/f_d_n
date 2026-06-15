<?php

namespace App\Replication;

use App\Entity\Boleto;
use App\Entity\Salida;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Handles bidirectional synchronization of common-interest data
 * between the new (PostgreSQL) and legacy (SQL Server) systems.
 *
 * Flow: New system event → ReplicationService → legacy system
 * Reverse: SincronizarCommand + Migrador → new system
 *
 * Key entities to sync: salida, boleto, asiento occupancy.
 *
 * Registered as a Doctrine event subscriber — postPersist/postUpdate
 * trigger replication to the legacy system automatically.
 */
class ReplicationService implements EventSubscriber {
    public function __construct(
        private Connection $newConn,
        private \PDO $oldPdo,
    ) {
        $this->oldPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->oldPdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    /**
     * Sync a new Salida to the legacy system.
     */
    public function syncSalidaToLegacy(int $salidaId): void {
        $salida = $this->newConn->fetchAssociative(
            'SELECT s.*, t.legacy_id AS ruta_legacy_id FROM salida s LEFT JOIN trayecto t ON t.id = s.ruta_id WHERE s.id = :id',
            ['id' => $salidaId]
        );
        if (!$salida) {
            return;
        }

        $legacySalidaId = $salida['legacy_id'];
        if ($legacySalidaId) {
            $existing = $this->fetchOldOne('SELECT id FROM salida WHERE id = :id', ['id' => $legacySalidaId]);
            if ($existing) {
                $this->updateOld(
                    'UPDATE salida SET fecha = :fecha, bus_codigo = :bus, estado_id = :estado WHERE id = :id',
                    [
                        'fecha' => $salida['hora_partida'],
                        'bus' => $this->getLegacyBusCode($salida['bus_id']),
                        'estado' => $salida['activa'] ? 1 : 4,
                        'id' => $legacySalidaId,
                    ]
                );
                return;
            }
        }

        $maxId = $this->fetchOldOne('SELECT MAX(id) FROM salida');
        $newId = ($maxId ? (int) $maxId : 0) + 1;

        $this->insertOld(
            'INSERT INTO salida (id, fecha, empresa_id, estado_id, bus_codigo) VALUES (:id, :fecha, :empresa, :estado, :bus)',
            [
                'id' => $newId,
                'fecha' => $salida['hora_partida'],
                'empresa' => $this->getLegacyEmpresaId((int) $salida['empresa_id']),
                'estado' => $salida['activa'] ? 1 : 4,
                'bus' => $this->getLegacyBusCode($salida['bus_id']),
            ]
        );

        $this->newConn->executeStatement(
            'UPDATE salida SET legacy_id = :lid WHERE id = :id',
            ['lid' => $newId, 'id' => $salidaId]
        );
    }

    /**
     * Sync a new Boleto to the legacy system.
     */
    public function syncBoletoToLegacy(int $boletoId): void {
        $boleto = $this->newConn->fetchAssociative(
            'SELECT b.*, s.legacy_id AS salida_legacy_id, e.id AS estacion_id
             FROM boleto b
             JOIN salida s ON s.id = b.salida_id
             LEFT JOIN enclave e ON e.id = b.estacion_id
             WHERE b.id = :id',
            ['id' => $boletoId]
        );
        if (!$boleto) {
            return;
        }

        $legacyBoletoId = $boleto['legacy_id'];
        if ($legacyBoletoId) {
            $existing = $this->fetchOldOne('SELECT id FROM boleto WHERE id = :id', ['id' => $legacyBoletoId]);
            if ($existing) {
                return;
            }
        }

        $maxId = $this->fetchOldOne('SELECT MAX(id) FROM boleto');
        $newId = ($maxId ? (int) $maxId : 0) + 1;

        $this->insertOld(
            'INSERT INTO boleto (id, fecha_creacion, salida_id, cliente_boleto, estacion_origen_id, precio_calculado) VALUES (:id, :fecha, :salida, :cliente, :estacion, :precio)',
            [
                'id' => $newId,
                'fecha' => $boleto['fecha_compra'] ?? date('Y-m-d H:i:s'),
                'salida' => $boleto['salida_legacy_id'] ?? 0,
                'cliente' => $this->getLegacyClienteId((int) $boleto['cliente_id']),
                'estacion' => $boleto['estacion_id'] ?? 0,
                'precio' => 0,
            ]
        );

        $this->newConn->executeStatement(
            'UPDATE boleto SET legacy_id = :lid WHERE id = :id',
            ['lid' => $newId, 'id' => $boletoId]
        );
    }

    /**
     * Check if a seat is available for a given departure in both systems.
     * Returns true if available, false if already sold.
     */
    public function checkAsientoDisponible(int $salidaId, int $asientoId): bool {
        $salida = $this->newConn->fetchAssociative(
            'SELECT legacy_id FROM salida WHERE id = :id',
            ['id' => $salidaId]
        );
        if (!$salida || !$salida['legacy_id']) {
            return true;
        }

        $legacySalidaId = (int) $salida['legacy_id'];
        $legacyAsiento = $this->newConn->fetchOne(
            'SELECT legacy_id FROM asiento WHERE id = :id',
            ['id' => $asientoId]
        );

        if ($legacyAsiento) {
            $sold = $this->fetchOldOne(
                'SELECT 1 FROM boleto WHERE salida_id = :sid AND asiento_bus_id = :aid AND estado_id != 4',
                ['sid' => $legacySalidaId, 'aid' => $legacyAsiento]
            );
            if ($sold) {
                return false;
            }
        }

        $soldNew = $this->newConn->fetchOne(
            'SELECT 1 FROM boleto_asiento ba 
             JOIN boleto b ON b.id = ba.boleto_id 
             WHERE b.salida_id = :sid AND ba.asiento_id = :aid AND b.status_id IS DISTINCT FROM :cancelado
             LIMIT 1',
            ['sid' => $salidaId, 'aid' => $asientoId, 'cancelado' => 4]
        );

        return $soldNew === false;
    }

    // ─── Doctrine event subscriber ────────────────────────────────

    public function getSubscribedEvents(): array {
        return [
            Events::postPersist,
            Events::postUpdate,
        ];
    }

    public function postPersist(PostPersistEventArgs $args): void {
        $entity = $args->getObject();
        if ($entity instanceof Salida) {
            $this->syncSalidaToLegacy((int) $entity->getId());
        } elseif ($entity instanceof Boleto) {
            $this->syncBoletoToLegacy((int) $entity->getId());
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void {
        $entity = $args->getObject();
        if ($entity instanceof Salida) {
            $this->syncSalidaToLegacy((int) $entity->getId());
        }
    }

    // ─── Private helpers ───────────────────────────────────────────

    private function getLegacyBusCode(?int $busId): ?string {
        if (!$busId) {
            return null;
        }
        $result = $this->newConn->fetchOne('SELECT legacy_id FROM bus WHERE id = :id', ['id' => $busId]);
        return $result ?: null;
    }

    private function getLegacyEmpresaId(int $empresaId): ?int {
        return $this->newConn->fetchOne('SELECT id FROM empresa WHERE id = :id', ['id' => $empresaId]) ?: null;
    }

    private function getLegacyClienteId(int $clienteId): ?int {
        return $this->newConn->fetchOne('SELECT id FROM cliente WHERE id = :id', ['id' => $clienteId]) ?: null;
    }

    private function fetchOldOne(string $sql, array $params = []): mixed {
        $stmt = $this->oldPdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ? reset($result) : false;
    }

    private function insertOld(string $sql, array $params = []): void {
        $stmt = $this->oldPdo->prepare($sql);
        $stmt->execute($params);
    }

    private function updateOld(string $sql, array $params = []): void {
        $stmt = $this->oldPdo->prepare($sql);
        $stmt->execute($params);
    }
}
