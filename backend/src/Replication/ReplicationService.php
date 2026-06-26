<?php

namespace App\Replication;

use App\Entity\Boleto;
use App\Entity\Servicio;
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
 * Key entities to sync: servicio, boleto, asiento occupancy.
 *
 * Registered as a Doctrine event subscriber — postPersist/postUpdate
 * trigger replication to the legacy system automatically.
 */
class ReplicationService implements EventSubscriber {
    public function __construct(
        private Connection $newConn,
        #[Target('oldPdo')] private \PDO $oldPdo,
    ) {
        $this->oldPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->oldPdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    /**
     * Sync a new Servicio to the legacy system.
     */
    public function syncServicioToLegacy(int $servicioId): void {
        $servicio = $this->newConn->fetchAssociative(
            'SELECT s.*, t.legacy_id AS ruta_legacy_id FROM servicio s LEFT JOIN trayecto t ON t.id = s.ruta_id WHERE s.id = :id',
            ['id' => $servicioId]
        );
        if (!$servicio) {
            return;
        }

        $legacyServicioId = $servicio['legacy_id'];
        if ($legacyServicioId) {
            $existing = $this->fetchOldOne('SELECT id FROM servicio WHERE id = :id', ['id' => $legacyServicioId]);
            if ($existing) {
                $this->updateOld(
                    'UPDATE servicio SET fecha = :fecha, bus_codigo = :bus, estado_id = :estado WHERE id = :id',
                    [
                        'fecha' => $servicio['hora_partida'],
                        'bus' => $this->getLegacyBusCode($servicio['bus_id']),
                        'estado' => $servicio['activa'] ? 1 : 4,
                        'id' => $legacyServicioId,
                    ]
                );
                return;
            }
        }

        $maxId = $this->fetchOldOne('SELECT MAX(id) FROM servicio');
        $newId = ($maxId ? (int) $maxId : 0) + 1;

        $this->insertOld(
            'INSERT INTO servicio (id, fecha, empresa_id, estado_id, bus_codigo) VALUES (:id, :fecha, :empresa, :estado, :bus)',
            [
                'id' => $newId,
                'fecha' => $servicio['hora_partida'],
                'empresa' => $this->getLegacyEmpresaId((int) $servicio['empresa_id']),
                'estado' => $servicio['activa'] ? 1 : 4,
                'bus' => $this->getLegacyBusCode($servicio['bus_id']),
            ]
        );

        $this->newConn->executeStatement(
            'UPDATE servicio SET legacy_id = :lid WHERE id = :id',
            ['lid' => $newId, 'id' => $servicioId]
        );
    }

    /**
     * Sync a new Boleto to the legacy system.
     */
    public function syncBoletoToLegacy(int $boletoId): void {
        $boleto = $this->newConn->fetchAssociative(
            'SELECT b.*, s.legacy_id AS servicio_legacy_id, e.id AS estacion_id
             FROM boleto b
             JOIN servicio s ON s.id = b.servicio_id
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
            'INSERT INTO boleto (id, fecha_creacion, servicio_id, cliente_boleto, estacion_origen_id, precio_calculado) VALUES (:id, :fecha, :servicio, :cliente, :estacion, :precio)',
            [
                'id' => $newId,
                'fecha' => $boleto['fecha_compra'] ?? date('Y-m-d H:i:s'),
                'servicio' => $boleto['servicio_legacy_id'] ?? 0,
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
    public function checkAsientoDisponible(int $servicioId, int $asientoId): bool {
        $servicio = $this->newConn->fetchAssociative(
            'SELECT legacy_id FROM servicio WHERE id = :id',
            ['id' => $servicioId]
        );
        if (!$servicio || !$servicio['legacy_id']) {
            return true;
        }

        $legacyServicioId = (int) $servicio['legacy_id'];
        $legacyAsiento = $this->newConn->fetchOne(
            'SELECT legacy_id FROM asiento WHERE id = :id',
            ['id' => $asientoId]
        );

        if ($legacyAsiento) {
            $sold = $this->fetchOldOne(
                'SELECT 1 FROM boleto WHERE servicio_id = :sid AND asiento_bus_id = :aid AND estado_id != 4',
                ['sid' => $legacyServicioId, 'aid' => $legacyAsiento]
            );
            if ($sold) {
                return false;
            }
        }

        $soldNew = $this->newConn->fetchOne(
            'SELECT 1 FROM boleto_asiento ba 
             JOIN boleto b ON b.id = ba.boleto_id 
             WHERE b.servicio_id = :sid AND ba.asiento_id = :aid AND b.status_id IS DISTINCT FROM :cancelado
             LIMIT 1',
            ['sid' => $servicioId, 'aid' => $asientoId, 'cancelado' => 4]
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
        if ($entity instanceof Servicio) {
            $this->syncServicioToLegacy((int) $entity->getId());
        } elseif ($entity instanceof Boleto) {
            $this->syncBoletoToLegacy((int) $entity->getId());
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void {
        $entity = $args->getObject();
        if ($entity instanceof Servicio) {
            $this->syncServicioToLegacy((int) $entity->getId());
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
