<?php

namespace App\Migration;

use Doctrine\DBAL\Connection;

class Migrador {
    public function __construct(
        private Connection $newConn,
        private \PDO $oldPdo,
        private Mapeador $mapeador,
        private Limpiador $limpiador,
    ) {
        $this->oldPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->oldPdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    public function migrarBoletos(int $cantidad, bool $limpiar = false): array {
        if ($limpiar) {
            $this->limpiador->limpiar();
        }

        $contadores = [
            'empresa' => 0,
            'estacion' => 0,
            'bus' => 0,
            'asiento' => 0,
            'cliente' => 0,
            'trayecto' => 0,
            'tarifa' => 0,
            'salida' => 0,
            'boleto' => 0,
            'usuarios' => 0
        ];

        $offset = 0;
        $migrados = 0;

        while ($migrados < $cantidad) {
            $boletosOld = $this->fetchBoletos($cantidad, $offset);
            if (empty($boletosOld)) {
                break;
            }

            foreach ($boletosOld as $boletoOld) {
                $boletoLegacy = (string) $boletoOld['id'];

                if ($this->yaMigrado('boleto', $boletoLegacy)) {
                    continue;
                }

                $this->newConn->beginTransaction();
                try {
                    $this->migrarBoleto($boletoOld, $contadores);
                    $this->newConn->commit();
                } catch (\Throwable $e) {
                    $this->newConn->rollBack();
                    throw new \RuntimeException(
                        sprintf('Error migrando boleto %s: %s', $boletoLegacy, $e->getMessage()),
                        0,
                        $e
                    );
                }

                $migrados++;
                if ($migrados >= $cantidad) {
                    break;
                }
            }

            $offset += $cantidad;
        }

        return $contadores;
    }

    public function getUltimaFechaMigracion(): ?string {
        $sql = 'SELECT MAX(fecha_compra) FROM boleto';
        $result = $this->newConn->fetchOne($sql);

        return $result ?: null;
    }

    public function migrarBoletosRecientes(int $max = 50): array {
        $ultimaFecha = $this->getUltimaFechaMigracion();

        if (!$ultimaFecha) {
            return $this->migrarBoletos($max);
        }

        $contadores = [
            'empresa' => 0,
            'estacion' => 0,
            'bus' => 0,
            'asiento' => 0,
            'cliente' => 0,
            'trayecto' => 0,
            'tarifa' => 0,
            'salida' => 0,
            'boleto' => 0,
        ];

        $sql = 'SELECT * FROM boleto WHERE fecha_creacion > :fecha ORDER BY fecha_creacion ASC';
        $boletosOld = $this->fetchOld($sql, ['fecha' => $ultimaFecha]);

        foreach ($boletosOld as $boletoOld) {
            $boletoLegacy = (string) $boletoOld['id'];

            if ($this->yaMigrado('boleto', $boletoLegacy)) {
                continue;
            }

            $this->newConn->beginTransaction();
            try {
                $this->migrarBoleto($boletoOld, $contadores);
                $this->newConn->commit();
            } catch (\Throwable $e) {
                $this->newConn->rollBack();
                throw new \RuntimeException(
                    sprintf('Error sincronizando boleto %s: %s', $boletoLegacy, $e->getMessage()),
                    0,
                    $e
                );
            }
        }

        return $contadores;
    }

    private function yaMigrado(string $tabla, string $legacyId): bool {
        $sql = match ($tabla) {
            'estacion' => 'SELECT 1 FROM enclave WHERE legacy_id = :lid',
            default => "SELECT 1 FROM {$tabla} WHERE legacy_id = :lid",
        };
        $result = $this->newConn->fetchOne($sql, ['lid' => $legacyId]);

        return $result !== false;
    }

    private function getNewId(string $tabla, string $legacyId): ?int {
        $sql = match ($tabla) {
            'estacion' => 'SELECT id FROM enclave WHERE legacy_id = :lid',
            default => "SELECT id FROM {$tabla} WHERE legacy_id = :lid",
        };
        $result = $this->newConn->fetchOne($sql, ['lid' => $legacyId]);

        return $result !== false ? (int) $result : null;
    }

    // ─── Fetch helpers ─────────────────────────────────────────────

    private function fetchOld(string $sql, array $params = []): array {
        $stmt = $this->oldPdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function fetchBoletos(int $limit, int $offset): array {
        $sql = sprintf(
            'SELECT * FROM boleto ORDER BY fecha_creacion DESC OFFSET %d ROWS FETCH NEXT %d ROWS ONLY',
            $offset,
            $limit
        );

        return $this->fetchOld($sql);
    }

    private function fetchSalida(int|string $id): ?array {
        $result = $this->fetchOld('SELECT * FROM salida WHERE id = :id', ['id' => $id]);

        return $result[0] ?? null;
    }

    private function fetchItinerario(int|string $id): ?array {
        $result = $this->fetchOld('SELECT * FROM itineario WHERE id = :id', ['id' => $id]);

        return $result[0] ?? null;
    }

    private function fetchRuta(string $codigo): ?array {
        $result = $this->fetchOld('SELECT * FROM ruta WHERE codigo = :codigo', ['codigo' => $codigo]);

        return $result[0] ?? null;
    }

    private function fetchRutaEstacionItems(string $rutaCodigo): array {
        return $this->fetchOld(
            'SELECT * FROM ruta_estacion_item WHERE ruta_codigo = :codigo ORDER BY posicion ASC',
            ['codigo' => $rutaCodigo]
        );
    }

    private function fetchEmpresa(int|string $id): ?array {
        $result = $this->fetchOld('SELECT * FROM empresa WHERE id = :id', ['id' => $id]);

        return $result[0] ?? null;
    }

    private function fetchEstacion(int|string $id): ?array {
        $result = $this->fetchOld('SELECT * FROM estacion WHERE id = :id', ['id' => $id]);

        return $result[0] ?? null;
    }

    private function fetchBus(string $codigo): ?array {
        $result = $this->fetchOld('SELECT * FROM bus WHERE codigo = :codigo', ['codigo' => $codigo]);

        return $result[0] ?? null;
    }

    private function fetchTipoBus(int|string $id): ?array {
        $result = $this->fetchOld('SELECT * FROM bus_tipo WHERE id = :id', ['id' => $id]);

        return $result[0] ?? null;
    }

    private function fetchAsientosPorTipoBus(int|string $tipoBusId): array {
        return $this->fetchOld(
            'SELECT ba.*, ca.nombre AS clase_nombre FROM bus_asiento ba LEFT JOIN clase_asiento ca ON ca.id = ba.clase_id WHERE ba.tipoBus_id = :id',
            ['id' => $tipoBusId]
        );
    }

    private function fetchCliente(int|string $id): ?array {
        $result = $this->fetchOld('SELECT * FROM cliente WHERE id = :id', ['id' => $id]);

        return $result[0] ?? null;
    }

    private function fetchTarifaBoletoPorRuta(?int $origenId, ?int $destinoId): ?array {
        if (!$origenId || !$destinoId) {
            return null;
        }

        $result = $this->fetchOld(
            'SELECT TOP 1 * FROM tarifas_boleto WHERE estacion_origen_id = :origen AND estacion_destino_id = :destino ORDER BY fechaEfectividad DESC',
            ['origen' => $origenId, 'destino' => $destinoId]
        );

        return $result[0] ?? null;
    }

    private function fetchTipoBusPorBus(string $busCodigo): ?array {
        $result = $this->fetchOld(
            'SELECT bt.* FROM bus_tipo bt INNER JOIN bus b ON b.tipo_id = bt.id WHERE b.codigo = :codigo',
            ['codigo' => $busCodigo]
        );

        return $result[0] ?? null;
    }

    // ─── Migration helpers ─────────────────────────────────────────

    private function migrarBoleto(array $boletoOld, array &$contadores): void {
        $existing = $this->getNewId('salida', $boletoOld['salida_id']);
        if (!$existing) {

            $salidaOld = $this->fetchSalida($boletoOld['salida_id']);
            if (!$salidaOld) {
                return;
            }
            $itinerarioOld = $salidaOld['itinerario_id'] ? $this->fetchItinerario($salidaOld['itinerario_id']) : null;
            $empresaId = $this->migrarEmpresa($salidaOld['empresa_id'], $contadores);
            $busId = null;
            $asientoId = null;
            if ($salidaOld['bus_codigo']) {
                $busId = $this->migrarBus($salidaOld['bus_codigo'], $empresaId, $contadores);
                $asientoId = $this->migrarAsientosParaBus($salidaOld['bus_codigo'], $busId, $contadores);
            }
            $trayectoId = null;
            if ($itinerarioOld && $itinerarioOld['ruta_codigo']) {
                $trayectoId = $this->migrarTrayecto($itinerarioOld['ruta_codigo'], $contadores);
            }

            $tarifaId = $this->migrarTarifa(
                $boletoOld['estacion_origen_id'],
                $boletoOld['estacion_destino_id'],
                $salidaOld['empresa_id'],
                $contadores
            );

            $salidaId = $this->migrarSalidaRecord(
                $boletoOld['salida_id'],
                $salidaOld,
                $trayectoId,
                $busId,
                $empresaId,
                $tarifaId,
                $contadores
            );
        } else {
            $salidaId = $existing;
            // $itinerarioOld = $salidaOld['itinerario_id'] ? $this->fetchItinerario($salidaOld['itinerario_id']) : null;
            // $empresaId = $this->get($salidaOld['empresa_id'], $contadores);
        }
        $estacionId = $this->migrarEstacion($boletoOld['estacion_origen_id'], $contadores);

        $clienteId = $this->migrarCliente($boletoOld, $contadores);


        $boletoId = $this->insertarBoleto($boletoOld, $salidaId, $clienteId, $estacionId, $trayectoId);

        if ($asientoId && $boletoId) {
            $this->newConn->executeStatement(
                'INSERT INTO boleto_asiento (boleto_id, asiento_id) VALUES (:bid, :aid) ON CONFLICT DO NOTHING',
                ['bid' => $boletoId, 'aid' => $asientoId]
            );
        }

        $contadores['boleto']++;
    }

    private function migrarEmpresa(?int $oldId, array &$contadores): ?int {
        if (!$oldId) {
            return null;
        }

        $legacyId = (string) $oldId;
        $existing = $this->getNewId('empresa', $legacyId);
        if ($existing) {
            return $existing;
        }

        $old = $this->fetchEmpresa($oldId);
        if (!$old) {
            return null;
        }

        $data = $this->mapeador->empresa($old);
        $id = $this->newConn->fetchOne(
            'INSERT INTO empresa (nombre, nif, direccion, telefono, email, legacy_id) VALUES (:nombre, :nif, :direccion, :telefono, :email, :legacy_id) RETURNING id',
            $data
        );
        $contadores['empresa']++;

        return (int) $id;
    }

    private function migrarEstacion(?int $oldId, array &$contadores): ?int {
        if (!$oldId) {
            return null;
        }

        $legacyId = (string) $oldId;
        // $existing = $this->getNewId('estacion', $legacyId);
        // if ($existing) {
        //     return $existing;
        // }

        $old = $this->fetchEstacion($oldId);
        if (!$old) {
            return null;
        }

        $data = $this->mapeador->estacion($old);
        $id = $this->newConn->fetchOne(
            "INSERT INTO enclave (tipo, nombre, direccion, latitud, longitud, legacy_id) VALUES ('estacion', :nombre, :direccion, :latitud, :longitud, :legacy_id) RETURNING id",
            $data
        );
        $contadores['estacion']++;

        return (int) $id;
    }

    private function migrarBus(string $codigo, ?int $empresaId, array &$contadores): ?int {
        if (!$codigo || !$empresaId) {
            return null;
        }

        $existing = $this->getNewId('bus', $codigo);
        if ($existing) {
            return $existing;
        }

        $old = $this->fetchBus($codigo);
        if (!$old) {
            return null;
        }

        $tipo = $this->fetchTipoBusPorBus($codigo);
        $data = $this->mapeador->bus($old, $empresaId);
        $data['gama'] = $tipo['descripcion'] ?? null;

        $id = $this->newConn->fetchOne(
            'INSERT INTO bus (matricula, gama, empresa_id, legacy_id) VALUES (:matricula, :gama, :empresa_id, :legacy_id) RETURNING id',
            $data
        );
        $contadores['bus']++;

        return (int) $id;
    }

    private function migrarAsientosParaBus(string $busCodigo, int $busId, array &$contadores): ?int {
        $tipo = $this->fetchTipoBusPorBus($busCodigo);
        if (!$tipo) {
            return null;
        }

        $asientosOld = $this->fetchAsientosPorTipoBus($tipo['id']);
        if (empty($asientosOld)) {
            return null;
        }

        $firstAsientoId = null;
        $inserted = 0;

        foreach ($asientosOld as $asientoOld) {
            $asientoLegacy = (string) $asientoOld['id'];

            if ($this->yaMigrado('asiento', $asientoLegacy)) {
                if (!$firstAsientoId) {
                    $firstAsientoId = $this->getNewId('asiento', $asientoLegacy);
                }
                continue;
            }

            $data = $this->mapeador->asiento($asientoOld, $busId);
            $newId = $this->newConn->fetchOne(
                'INSERT INTO asiento (numero, clase, fila, columna, bus_id, legacy_id) VALUES (:numero, :clase, :fila, :columna, :bus_id, :legacy_id) RETURNING id',
                $data
            );

            if (!$firstAsientoId) {
                $firstAsientoId = (int) $newId;
            }
            $inserted++;
        }

        $contadores['asiento'] += $inserted;

        return $firstAsientoId;
    }

    private function migrarCliente(array $boletoOld, array &$contadores): ?int {
        $clienteId = $boletoOld['cliente_boleto'] ?? $boletoOld['cliente_documento'] ?? null;
        if (!$clienteId) {
            return null;
        }

        $legacyId = (string) $clienteId;
        $existing = $this->getNewId('cliente', $legacyId);
        if ($existing) {
            return $existing;
        }

        $old = $this->fetchCliente($clienteId);
        if (!$old) {
            return null;
        }

        $data = $this->mapeador->cliente($old);
        $id = $this->newConn->fetchOne(
            'INSERT INTO cliente (nombre, apellidos, nif, email, telefono, legacy_id) VALUES (:nombre, :apellidos, :nif, :email, :telefono, :legacy_id) RETURNING id',
            $data
        );
        $contadores['cliente']++;

        return (int) $id;
    }

    private function migrarTrayecto(string $rutaCodigo, array &$contadores): ?int {
        $existing = $this->getNewId('trayecto', $rutaCodigo);
        if ($existing) {
            return $existing;
        }

        $oldRuta = $this->fetchRuta($rutaCodigo);
        if (!$oldRuta) {
            return null;
        }

        $origenId = $this->migrarEstacion($oldRuta['estacion_origen_id'], $contadores);
        $destinoId = $this->migrarEstacion($oldRuta['estacion_destino_id'], $contadores);
        if (!$origenId || !$destinoId) {
            return null;
        }

        $data = $this->mapeador->trayecto($oldRuta, $origenId, $destinoId, true);
        $trayectoId = $this->newConn->fetchOne(
            'INSERT INTO trayecto (origen_id, destino_id, distancia_km, duracion_estimada_minutos, activo, es_ruta, legacy_id) VALUES (:origen_id, :destino_id, :distancia_km, :duracion_estimada_minutos, :activo, :es_ruta, :legacy_id) RETURNING id',
            $data
        );
        $contadores['trayecto']++;

        $this->migrarSubTrayectos($rutaCodigo, (int) $trayectoId, $origenId, $destinoId, $contadores);

        return (int) $trayectoId;
    }

    private function migrarSubTrayectos(string $rutaCodigo, int $trayectoPadreId, int $rutaOrigenId, int $rutaDestinoId, array &$contadores): void {
        $items = $this->fetchRutaEstacionItems($rutaCodigo);
        if (empty($items)) {
            return;
        }

        $stationIds = [$rutaOrigenId];
        foreach ($items as $item) {
            $estId = $this->migrarEstacion($item['estacion_id'], $contadores);
            if ($estId) {
                $stationIds[] = $estId;
            }
        }
        $stationIds[] = $rutaDestinoId;

        for ($i = 0; $i < count($stationIds) - 1; $i++) {
            $subLegacy = sprintf('SUB-%s-%d', $rutaCodigo, $i);
            $subExisting = $this->getNewId('trayecto', $subLegacy);
            if ($subExisting) {
                $this->linkTrayectoHijo($trayectoPadreId, $subExisting);
                continue;
            }

            $subId = $this->newConn->fetchOne(
                'INSERT INTO trayecto (origen_id, destino_id, activo, es_ruta, legacy_id) VALUES (:origen_id, :destino_id, true, false, :legacy_id) RETURNING id',
                [
                    'origen_id' => $stationIds[$i],
                    'destino_id' => $stationIds[$i + 1],
                    'legacy_id' => $subLegacy,
                ]
            );

            $this->linkTrayectoHijo($trayectoPadreId, (int) $subId);
            $contadores['trayecto']++;
        }
    }

    private function linkTrayectoHijo(int $padreId, int $hijoId): void {
        $this->newConn->executeStatement(
            'INSERT INTO trayecto_trayecto (trayecto_source, trayecto_target) VALUES (:padre, :hijo) ON CONFLICT DO NOTHING',
            ['padre' => $padreId, 'hijo' => $hijoId]
        );
    }

    private function migrarTarifa(?int $origenId, ?int $destinoId, ?int $empresaOldId, array &$contadores): ?int {
        if (!$origenId || !$destinoId || !$empresaOldId) {
            return null;
        }

        $tarifaOld = $this->fetchTarifaBoletoPorRuta($origenId, $destinoId);
        if (!$tarifaOld) {
            return null;
        }

        $legacyId = (string) $tarifaOld['id'];
        $existing = $this->getNewId('tarifa', $legacyId);
        if ($existing) {
            return $existing;
        }

        $empresaId = $this->migrarEmpresa($empresaOldId, $contadores);
        if (!$empresaId) {
            return null;
        }

        $data = $this->mapeador->tarifa($tarifaOld, $empresaId);
        $tarifaId = $this->newConn->fetchOne(
            'INSERT INTO tarifa (nombre, precio_clase_a, precio_clase_b, empresa_id, bus_id, legacy_id) VALUES (:nombre, :precio_clase_a, :precio_clase_b, :empresa_id, :bus_id, :legacy_id) RETURNING id',
            $data
        );

        $contadores['tarifa']++;

        return (int) $tarifaId;
    }

    private function migrarSalidaRecord(int|string $salidaOldId, array $salidaOld, ?int $trayectoId, ?int $busId, int $empresaId, ?int $tarifaId, array &$contadores): ?int {
        $legacyId = (string) $salidaOldId;
        $existing = $this->getNewId('salida', $legacyId);
        if ($existing) {
            return $existing;
        }

        $data = $this->mapeador->salida($salidaOld, $trayectoId, $busId, $empresaId, $tarifaId);
        $id = $this->newConn->fetchOne(
            'INSERT INTO salida (hora_partida, ruta_id, bus_id, empresa_id, tarifa_id, activa, legacy_id) VALUES (:hora_partida, :ruta_id, :bus_id, :empresa_id, :tarifa_id, :activa, :legacy_id) RETURNING id',
            $data
        );
        $contadores['salida']++;

        return (int) $id;
    }

    private function insertarBoleto(array $boletoOld, ?int $salidaId, ?int $clienteId, ?int $estacionId, ?int $trayectoId): ?int {
        $data = $this->mapeador->boleto(
            $boletoOld,
            $salidaId,
            $clienteId,
            $estacionId,
            $trayectoId,
            $boletoOld['asiento_bus_id'] ?? null
        );

        return (int) $this->newConn->fetchOne(
            'INSERT INTO boleto (fecha_compra, salida_id, trayecto_id, cliente_id, estacion_id, usuario_creador, legacy_id) VALUES (:fecha_compra, :salida_id, :trayecto_id, :cliente_id, :estacion_id, :usuario_creador, :legacy_id) RETURNING id',
            $data
        );
    }
}
