<?php

namespace App\Migration;

use App\EntitySistemaFdn\Salida;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

class Migrador {
    /**
     * Tables that use old PK as new id (no legacy_id column).
     */
    private const ID_MAP = ['empresa', 'enclave', 'asiento', 'cliente', 'usuario', 'tarifa'];

    /**
     * Tables that keep legacy_id column (old PK is string or variable data).
     */
    private const LEGACY_MAP = ['bus', 'trayecto', 'servicio', 'boleto'];

    public function __construct(
        private Connection $newConn,
        // #[Target('oldPdo')] private \PDO $oldPdo,
        private Mapeador $mapeador,
        private Limpiador $limpiador,
        private EntityManagerInterface $entityManager,
        #[Target('doctrine.orm.systemfdn_entity_manager')]
        private EntityManagerInterface $systemfdnEm,

    ) {
        // $this->oldPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        // $this->oldPdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    public function migrarBoletos(int $cantidad, bool $limpiar = false, ?OutputInterface $output = null): array {
        if ($limpiar) {
            $this->limpiador->limpiar();
        }

        $contadores = $this->contadoresIniciales();
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
                if ($output) {
                    $output->write(sprintf("\r<info>Boletos... %d/%d</info>", $migrados, $cantidad));
                }
                if ($migrados >= $cantidad) {
                    break;
                }
            }

            $offset += $cantidad;
        }

        if ($output) {
            $output->writeln('');
        }
        return $contadores;
    }

    public function migrarServicio(?OutputInterface $output = null): array {

        $conn = $this->systemfdnEm->getConnection();

        try {
            $conn->executeQuery('SELECT 1')->fetchOne();
            dump('Conectó correctamente');
        } catch (\Throwable $e) {
            dump(get_class($e));
            dump($e->getMessage());
            throw $e;
        }
        $repo = $this->systemfdnEm->getRepository(Salida::class);
        $result = $repo->createQueryBuilder('e')->where('e.fecha > :fecha')
            ->setParameter('fecha', '20260701')
            ->getQuery()
            ->getResult();
        return $result;
    }

    public function getUltimaFechaMigracion(): ?string {
        $sql = 'SELECT MAX(fecha_compra) FROM boleto';
        $result = $this->newConn->fetchOne($sql);

        return $result ?: null;
    }

    public function migrarBoletosRecientes(int $max = 50, ?OutputInterface $output = null): array {
        $ultimaFecha = $this->getUltimaFechaMigracion();

        if (!$ultimaFecha) {
            return $this->migrarBoletos($max, false, $output);
        }

        $contadores = $this->contadoresIniciales();
        $offset = 0;
        $migrados = 0;

        while ($migrados < $max) {
            $sql = sprintf(
                'SELECT * FROM boleto WHERE fecha_creacion > :fecha ORDER BY fecha_creacion ASC OFFSET %d ROWS FETCH NEXT %d ROWS ONLY',
                $offset,
                $max
            );
            $boletosOld = $this->fetchOld($sql, ['fecha' => $ultimaFecha]);

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
                        sprintf('Error sincronizando boleto %s: %s', $boletoLegacy, $e->getMessage()),
                        0,
                        $e
                    );
                }

                $migrados++;
                if ($output) {
                    $output->write(sprintf("\r<info>Boletos nuevos... %d/%d</info>", $migrados, $max));
                }
                if ($migrados >= $max) {
                    break;
                }
            }

            $offset += $max;
        }

        if ($output) {
            $output->writeln('');
        }
        return $contadores;
    }

    // ─── Existence checks ──────────────────────────────────────────

    /**
     * Check if a record exists by legacy_id (or by id for ID_MAP tables).
     */
    public function yaMigrado(string $tabla, string $legacyId): bool {
        $sql = match ($tabla) {
            'estacion' => 'SELECT 1 FROM enclave WHERE id = :lid',
            'empresa', 'asiento', 'cliente', 'usuario', 'tarifa' => "SELECT 1 FROM {$tabla} WHERE id = :lid",
            'bus' => "SELECT 1 FROM {$tabla} WHERE codigo = :lid",
            default => "SELECT 1 FROM {$tabla} WHERE legacy_id = :lid",
        };
        $result = $this->newConn->fetchOne($sql, ['lid' => $legacyId]);

        return $result !== false;
    }

    /**
     * Get the new DB id for a legacy record, searching by legacy_id or by id.
     */
    public function getNewId(string $tabla, string $legacyId, array $fields = ['id']): int | array | null {
        $select = implode(', ', $fields);

        $sql = match (true) {
            in_array($tabla, self::ID_MAP, true) || $tabla === 'estacion' => match ($tabla) {
                'estacion' => "SELECT {$select} FROM enclave WHERE id = :lid",
                default => "SELECT {$select} FROM \"{$tabla}\" WHERE id = :lid",
            },
            $tabla === 'bus' => "SELECT {$select} FROM bus WHERE codigo = :lid",
            default => "SELECT {$select} FROM \"{$tabla}\" WHERE legacy_id = :lid",
        };

        if (is_numeric($legacyId)) {
            $sql .= ' OR id = :lid';
        }

        if (count($fields) > 1 || $fields[0] !== 'id') {
            $result = $this->newConn->fetchAllAssociative($sql, ['lid' => $legacyId]);
            return $result[0] ?? null;
        }
        $result = $this->newConn->fetchOne($sql, ['lid' => $legacyId]);
        return $result !== false ? (int) $result : null;
    }

    // ─── Fetch helpers ─────────────────────────────────────────────

    private function fetchOld(string $sql, array $params = []): array {
        return $this->systemfdnEm->getConnection()->executeQuery($sql, $params)->fetchAllAssociative();
        // $stmt = $this->oldPdo->prepare($sql);
        // $stmt->execute($params);
        // return $stmt->fetchAll();
    }

    private function fetchBoletos(int $limit, int $offset): array {
        $sql = sprintf(
            'SELECT * FROM boleto ORDER BY fecha_creacion DESC OFFSET %d ROWS FETCH NEXT %d ROWS ONLY',
            $offset,
            $limit
        );
        return $this->fetchOld($sql);
    }

    private function fetchServicio(int|string $id): ?array {
        $result = $this->fetchOld('SELECT * FROM servicio WHERE id = :id', ['id' => $id]);
        return $result[0] ?? null;
    }

    private function fetchUsuario(int|string $id): ?array {
        $result = $this->fetchOld('SELECT * FROM custom_user WHERE id = :id', ['id' => $id]);
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

    // ─── Migration core ────────────────────────────────────────────

    private function migrarBoleto(array $boletoOld, array &$contadores): void {
        $existing = $this->getNewId('servicio', (string) $boletoOld['servicio_id'], ['id', 'recorrido_id']);
        if (!$existing) {
            $servicioOld = $this->fetchServicio($boletoOld['servicio_id']);
            if (!$servicioOld) {
                return;
            }
            $itinerarioOld = $servicioOld['itinerario_id'] ? $this->fetchItinerario($servicioOld['itinerario_id']) : null;
            $empresaId = $this->migrarEmpresa($servicioOld['empresa_id'], $contadores);

            $busId = null;
            if ($servicioOld['bus_codigo']) {
                $busId = $this->migrarBus($servicioOld['bus_codigo'], $empresaId, $contadores);
                $this->migrarAsientosParaBus($servicioOld['bus_codigo'], $busId, $contadores);
            }

            $trayectoId = null;
            if ($itinerarioOld && $itinerarioOld['ruta_codigo']) {
                $trayectoId = $this->migrarTrayecto($itinerarioOld['ruta_codigo'], $contadores);
            }

            $tarifaId = $this->migrarTarifa(
                $boletoOld['estacion_origen_id'],
                $boletoOld['estacion_destino_id'],
                $servicioOld['empresa_id'],
                $contadores
            );
            $idRecorrido = $this->migrarRecorrido($trayectoId, $tarifaId);
            $servicioId = $this->migrarServicioRecord(
                $boletoOld['servicio_id'],
                $servicioOld,
                $idRecorrido,
                $busId,
                $empresaId,
                $contadores
            );
            // $estacionId = $this->migrarEstacions($boletoOld['estacion_origen_id'], $contadores);
        } else {
            $servicioId = $existing['id'];
            $idRecorrido = $this->getNewId('servicio', (string) $servicioId, ['recorrido_id'])['recorrido_id'] ?? null;
            // $estacionId = $idRecorrido ? ($this->getNewId('recorrido', (string) $idRecorrido, ['origen_id'])['origen_id'] ?? null) : null;
        }
        $estacionId = $boletoOld['estacion_creacion_id'] ? $this->migrarEstacions($boletoOld['estacion_origen_id'], $contadores) : null;
        $clienteId = $this->migrarCliente($boletoOld, $contadores);
        $asientoId = $this->getNewId('asiento', (string) ($boletoOld['asiento_bus_id'] ?? ''));
        $usuarioId = $this->migrarUsuario($boletoOld, $contadores);

        $boletoId = $this->insertarBoleto($boletoOld, $servicioId, $clienteId, $estacionId, $idRecorrido, $usuarioId);

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
        if ($this->yaMigrado('empresa', $legacyId)) {
            return (int) $oldId;
        }

        $old = $this->fetchEmpresa($oldId);
        if (!$old) {
            return null;
        }

        $data = $this->mapeador->empresa($old);
        $this->newConn->executeStatement(
            'INSERT INTO empresa (id, nombre, nif, direccion, telefono, email) VALUES (:id, :nombre, :nif, :direccion, :telefono, :email)',
            $data
        );
        $contadores['empresa']++;

        return (int) $data['id'];
    }

    private function migrarEstacions(?int $oldId, array &$contadores): ?int {
        if (!$oldId) {
            return null;
        }

        $legacyId = (string) $oldId;
        if ($this->yaMigrado('estacion', $legacyId)) {
            return (int) $oldId;
        }

        $old = $this->fetchEstacion($oldId);
        if (!$old) {
            return null;
        }

        $data = $this->mapeador->estacion($old);
        $this->newConn->executeStatement(
            "INSERT INTO enclave (id, tipo, nombre, direccion, latitud, longitud) VALUES (:id, 'estacion', :nombre, :direccion, :latitud, :longitud)",
            $data
        );
        $contadores['estacion']++;

        return (int) $data['id'];
    }

    private function migrarBus(string $codigo, ?int $empresaId, array &$contadores): ?int {
        if (!$codigo || !$empresaId) {
            return null;
        }

        if ($this->yaMigrado('bus', $codigo)) {
            return $this->getNewId('bus', $codigo);
        }

        $old = $this->fetchBus($codigo);
        if (!$old) {
            return null;
        }

        $tipo = $this->fetchTipoBusPorBus($codigo);
        $data = $this->mapeador->bus($old, $empresaId);
        $data['gama'] = isset($tipo['descripcion']) ? mb_substr($tipo['descripcion'], 0, 50) : null;

        $fields = implode(', ', array_keys($data));
        $args = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));
        $id = $this->newConn->fetchOne(
            "INSERT INTO bus ({$fields}) VALUES ({$args}) RETURNING id",
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
            $this->newConn->executeStatement(
                'INSERT INTO asiento (id, numero, clase, fila, columna, bus_id) VALUES (:id, :numero, :clase, :fila, :columna, :bus_id)',
                $data
            );

            if (!$firstAsientoId) {
                $firstAsientoId = (int) $data['id'];
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
        if ($this->yaMigrado('cliente', $legacyId)) {
            return (int) $clienteId;
        }

        $old = $this->fetchCliente($clienteId);
        if (!$old) {
            return null;
        }

        $data = $this->mapeador->cliente($old);
        $this->newConn->executeStatement(
            'INSERT INTO cliente (id, nombre, apellido, nit, email, telefono) VALUES (:id, :nombre, :apellido, :nit, :email, :telefono)',
            $data
        );
        $contadores['cliente']++;

        return (int) $data['id'];
    }

    private function migrarUsuario(array $boletoOld, array &$contadores): ?int {
        $usuarioIds = [$boletoOld['usuario_creacion_id']];
        if (
            $boletoOld['usuario_actualizacion_id']
            && $boletoOld['usuario_actualizacion_id'] !== $boletoOld['usuario_creacion_id']
        ) {
            $usuarioIds[] = $boletoOld['usuario_actualizacion_id'];
        }

        $firstId = null;
        foreach ($usuarioIds as $idOld) {
            if (!$idOld) {
                continue;
            }
            $legacyId = (string) $idOld;

            if ($this->yaMigrado('usuario', $legacyId)) {
                if (!$firstId) {
                    $firstId = (int) $idOld;
                }
                continue;
            }

            $old = $this->fetchUsuario($idOld);
            if (!$old) {
                continue;
            }

            $data = $this->mapeador->usuario($old);
            $fields = implode(', ', array_keys($data));
            $args = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));
            $this->newConn->executeStatement(
                "INSERT INTO usuario ({$fields}) VALUES ({$args})",
                $data
            );
            $contadores['usuario']++;

            if (!$firstId) {
                $firstId = (int) $data['id'];
            }
        }

        return $firstId ?? ($boletoOld['usuario_creacion_id'] ?? null);
    }

    private function migrarTrayecto(string $rutaCodigo, array &$contadores): ?int {
        if ($this->yaMigrado('trayecto', $rutaCodigo)) {
            return $this->getNewId('trayecto', $rutaCodigo);
        }

        $oldRuta = $this->fetchRuta($rutaCodigo);
        if (!$oldRuta) {
            return null;
        }

        $origenId = $this->migrarEstacions($oldRuta['estacion_origen_id'], $contadores);
        $destinoId = $this->migrarEstacions($oldRuta['estacion_destino_id'], $contadores);
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
            $estId = $this->migrarEstacions($item['estacion_id'], $contadores);
            if ($estId) {
                $stationIds[] = $estId;
            }
        }
        $stationIds[] = $rutaDestinoId;

        for ($i = 0; $i < count($stationIds) - 1; $i++) {
            $subLegacy = sprintf('SUB-%s-%d', $rutaCodigo, $i);
            if ($this->yaMigrado('trayecto', $subLegacy)) {
                $subExisting = $this->getNewId('trayecto', $subLegacy);
                if ($subExisting) {
                    $this->linkTrayectoHijo($trayectoPadreId, $subExisting);
                }
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
    private function migrarRecorrido(?int $idTrayecto, ?int $idTarifa): ?int {
        $sql = 'Select id from recorrido where trayecto_id = :trayecto and tarifa_id = :tarifa';
        if ($result = $this->newConn->fetchOne($sql, ['trayecto' => $idTrayecto, 'tarifa' => $idTarifa])) {
            return (int) $result;
        }
        return $this->newConn->fetchOne(
            'INSERT INTO recorrido (trayecto_id, tarifa_id) VALUES (:trayecto, :tarifa) RETURNING id',
            ['trayecto' => $idTrayecto, 'tarifa' => $idTarifa]
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
        if ($this->yaMigrado('tarifa', $legacyId)) {
            return (int) $tarifaOld['id'];
        }

        $empresaId = $this->migrarEmpresa($empresaOldId, $contadores);
        if (!$empresaId) {
            return null;
        }

        $data = $this->mapeador->tarifa($tarifaOld, $empresaId);
        $this->newConn->executeStatement(
            'INSERT INTO tarifa (id, nombre, precio_clase_a_monto, precio_clase_b_monto, precio_clase_a_moneda, precio_clase_b_moneda, empresa_id, bus_id) VALUES (:id, :nombre, :precio_clase_a_monto, :precio_clase_b_monto,:precio_clase_a_moneda, :precio_clase_b_moneda, :empresa_id, :bus_id)',
            $data
        );
        $contadores['tarifa']++;

        return (int) $data['id'];
    }

    private function migrarServicioRecord(int|string $servicioOldId, array $servicioOld, ?int $idRecorrido, ?int $busId, int $empresaId, array &$contadores): ?int {
        $legacyId = (string) $servicioOldId;
        if ($this->yaMigrado('servicio', $legacyId)) {
            return $this->getNewId('servicio', $legacyId);
        }

        $data = $this->mapeador->servicio($servicioOld, $idRecorrido, $busId, $empresaId);
        $id = $this->newConn->fetchOne(
            'INSERT INTO servicio (hora_partida, recorrido_id, bus_id, empresa_id legacy_id) VALUES (:hora_partida, :recorrido_id, :bus_id, :empresa_id :legacy_id) RETURNING id',
            $data
        );
        $contadores['servicio']++;

        return (int) $id;
    }

    private function insertarBoleto(array $boletoOld, ?int $servicioId, ?int $clienteId, ?int $estacionId, ?int $idRecorrido, ?int $usuarioId): ?int {
        $data = $this->mapeador->boleto(
            $boletoOld,
            $servicioId,
            $clienteId,
            $estacionId,
            $idRecorrido,
            $boletoOld['asiento_bus_id'] ?? null,
            $usuarioId
        );

        return (int) $this->newConn->fetchOne(
            'INSERT INTO boleto (fecha_compra, servicio_id, recorrido_id, cliente_id, estacion_id, usuario_creador, legacy_id) VALUES (:fecha_compra, :servicio_id, :recorrido_id, :cliente_id, :estacion_id, :usuario_creador, :legacy_id) RETURNING id',
            $data
        );
    }

    private function contadoresIniciales(): array {
        return [
            'empresa' => 0,
            'estacion' => 0,
            'bus' => 0,
            'asiento' => 0,
            'cliente' => 0,
            'trayecto' => 0,
            'tarifa' => 0,
            'servicio' => 0,
            'boleto' => 0,
            'usuario' => 0,
        ];
    }
}
