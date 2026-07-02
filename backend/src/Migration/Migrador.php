<?php

namespace App\Migration;

use Doctrine\DBAL\Connection;
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
        #[Target('oldPdo')] private \PDO $oldPdo,
        private Mapeador $mapeador,

    ) {
        $this->oldPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->oldPdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    /**
     * @param \Closure|null $onProgress Optional callback invoked every N iterations: fn(int $done, int $total) => void
     */
    public function migrarServicio($servicios = 100, ?OutputInterface $output = null, ?\Closure $onProgress = null): array {
        $contadores = $this->contadoresIniciales();

        $salidas = $this->fetchSalidas($servicios);
        if ($output) {
            $output->writeln(sprintf('<info>Salidas a migrar: %d</info>', count($salidas)));
        }

        $total = count($salidas);
        foreach ($salidas as $i => $salida) {
            $legacyId = (string) $salida['id'];
            if ($this->yaMigrado('servicio', $legacyId)) {
                continue;
            }

            $this->newConn->beginTransaction();
            try {
                $empresaId = $this->migrarEmpresa(
                    $salida['empresa_id'] ?: ($salida['it_empresa_id'] ?? null),
                    $contadores
                );

                $trayectoId = null;
                if (!empty($salida['ruta_codigo'])) {
                    $trayectoId = $this->getNewId('trayecto', $salida['ruta_codigo']);
                    if (!$trayectoId) {
                        $trayectoId = $this->migrarTrayecto($salida['ruta_codigo'], $contadores);
                    }
                }

                $recorridoId = $trayectoId && $empresaId
                    ? $this->getOrCreateRecorrido($trayectoId, $empresaId, $salida['ruta_codigo'] ?? '', $contadores)
                    : null;

                $busId = null;
                if (!empty($salida['bus_codigo'])) {
                    $busId = $this->migrarBus($salida['bus_codigo'], $empresaId, $contadores);
                    if ($busId) {
                        $this->migrarAsientosParaBus($salida['bus_codigo'], $busId, $contadores);
                    }
                }

                $servicioId = $this->crearServicio($salida, $recorridoId, $busId, $empresaId, $contadores);

                if ($servicioId) {
                    $this->migrarBoletosDeSalida($salida['id'], $servicioId, $recorridoId, $busId, $contadores);
                }

                $this->newConn->commit();
            } catch (\Throwable $e) {
                $this->newConn->rollBack();
                if ($output) {
                    $output->writeln(sprintf('<error>Error salida %s: %s</error>', $legacyId, $e->getMessage()));
                }
            }

            if (($i + 1) % 50 === 0) {
                if ($onProgress) {
                    $onProgress();
                }
            }
            if ($output && ($i + 1) % 10 === 0) {
                $output->write(sprintf("\r<info>Salidas... %d/%d</info>", min($i + 1, $total), $total));
            }
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

    private function sanitizeUtf8(array $row): array {
        $clean = [];
        foreach ($row as $k => $v) {
            $clean[$k] = is_string($v) ? mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1') : $v;
        }
        return $clean;
    }

    private function fetchOld(string $sql, array $params = []): array {
        $stmt = $this->oldPdo->prepare($sql);
        $stmt->execute($params);
        return array_map(fn(array $r) => $this->sanitizeUtf8($r), $stmt->fetchAll());
    }

    private function fetchUsuario(int|string $id): ?array {
        $result = $this->fetchOld('SELECT * FROM custom_user WHERE id = :id', ['id' => $id]);
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
            'INSERT INTO empresa (id, nombre, nit, direccion, telefono, email) VALUES (:id, :nombre, :nit, :direccion, :telefono, :email)',
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
            'INSERT INTO cliente (id, nombre, apellido, nit, email, telefono) VALUES (:id, :nombre, :apellido, :nit, :email, :telefono) ON CONFLICT DO NOTHING',
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
                "INSERT INTO usuario ({$fields}) VALUES ({$args}) ON CONFLICT DO NOTHING",
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
            'INSERT INTO trayecto (origen_id, destino_id, distancia_km, duracion_estimada_minutos, activo,  legacy_id) VALUES (:origen_id, :destino_id, :distancia_km, :duracion_estimada_minutos, :activo,  :legacy_id) RETURNING id',
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
                'INSERT INTO trayecto (origen_id, destino_id, activo,  legacy_id) VALUES (:origen_id, :destino_id, true, false, :legacy_id) RETURNING id',
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

    // ─── Salida-driven migration helpers ───────────────────────────

    private function fetchSalidas(int $servicios): array {
        return $this->fetchOld(
            "SELECT TOP $servicios s.*, i.ruta_codigo, i.tipo_bus_id AS it_tipo_bus_id, i.empresa_id AS it_empresa_id
             FROM salida s
             LEFT JOIN itineario i ON i.id = s.itinerario_id
             WHERE s.estado_id in (1,2)
             ORDER BY s.fecha DESC"
        );
    }

    private function getOrCreateRecorrido(int $trayectoId, int $empresaId, string $rutaCodigo, array &$contadores): ?int {
        $existing = $this->newConn->fetchOne(
            'SELECT id FROM recorrido WHERE trayecto_id = :trayecto AND empresa_id = :empresa LIMIT 1',
            ['trayecto' => $trayectoId, 'empresa' => $empresaId]
        );
        if ($existing) {
            return (int) $existing;
        }

        $precioMonto = 0;
        $moneda = 'GTQ';

        $trayecto = $this->newConn->fetchAssociative(
            'SELECT origen_id, destino_id FROM trayecto WHERE id = :id',
            ['id' => $trayectoId]
        );

        if ($trayecto) {
            $tarifaOld = $this->fetchTarifaBoletoPorRuta((int) $trayecto['origen_id'], (int) $trayecto['destino_id']);
            if ($tarifaOld) {
                $precioMonto = (int) (($tarifaOld['tarifaValor'] ?? 0) * 100);
            }
        }

        $nombre = $rutaCodigo ? "Ruta-{$rutaCodigo}" : "Recorrido-{$trayectoId}";

        return (int) $this->newConn->fetchOne(
            'INSERT INTO recorrido (nombre, precio_clase_a_monto, precio_clase_a_moneda, precio_clase_b_monto, precio_clase_b_moneda, empresa_id, trayecto_id)
             VALUES (:nombre, :precio_a_monto, :precio_a_moneda, :precio_b_monto, :precio_b_moneda, :empresa_id, :trayecto_id)
             RETURNING id',
            [
                'nombre' => $nombre,
                'precio_a_monto' => $precioMonto,
                'precio_a_moneda' => $moneda,
                'precio_b_monto' => 0,
                'precio_b_moneda' => $moneda,
                'empresa_id' => $empresaId,
                'trayecto_id' => $trayectoId,
            ]
        );
    }

    private function crearServicio(array $salida, ?int $recorridoId, ?int $busId, ?int $empresaId, array &$contadores): ?int {
        $pilotoId = $salida['piloto_id'] ? (int) $salida['piloto_id'] : null;

        $data = $this->mapeador->servicio($salida, $recorridoId, $busId, $empresaId, $pilotoId);
        $id = $this->newConn->fetchOne(
            'INSERT INTO servicio (fecha, recorrido_id, bus_id, empresa_id, piloto_id, legacy_id) VALUES (:fecha, :recorrido_id, :bus_id, :empresa_id, :piloto_id, :legacy_id) RETURNING id',
            $data
        );
        $contadores['servicio']++;

        return (int) $id;
    }

    private function migrarBoletosDeSalida(int $salidaId, int $servicioId, ?int $recorridoId, ?int $busId, array &$contadores): void {
        $boletos = $this->fetchBoletosPorSalida($salidaId);
        if (empty($boletos)) {
            return;
        }

        foreach ($boletos as $boletoOld) {
            $boletoLegacy = (string) $boletoOld['id'];
            if ($this->yaMigrado('boleto', $boletoLegacy)) {
                continue;
            }

            $usuarioId = $this->migrarUsuario($boletoOld, $contadores);

            $clienteId = $this->migrarCliente($boletoOld, $contadores);
            if (!$clienteId) {
                $dummyId = $boletoOld['cliente_boleto'] ?? $boletoOld['cliente_documento'] ?? null;
                if ($dummyId) {
                    $this->newConn->executeStatement(
                        'INSERT INTO cliente (id, nombre, apellido) VALUES (:id, :nombre, :apellido) ON CONFLICT DO NOTHING',
                        ['id' => (int) $dummyId, 'nombre' => 'Cliente', 'apellido' => 'Migrado']
                    );
                    $clienteId = (int) $dummyId;
                    $contadores['cliente']++;
                }
            }
            if (!$clienteId) {
                continue;
            }

            $asientoId = null;
            if (!empty($boletoOld['asiento_bus_id'])) {
                $asientoId = $this->getNewId('asiento', (string) $boletoOld['asiento_bus_id']);
            }
            if (!$asientoId && $busId) {
                $asientoId = $this->newConn->fetchOne(
                    'SELECT id FROM asiento WHERE bus_id = :busId ORDER BY id LIMIT 1',
                    ['busId' => $busId]
                );
                $asientoId = $asientoId ? (int) $asientoId : null;
            }
            if (!$asientoId) {
                continue;
            }

            $ventaId = $this->crearVenta($usuarioId, $boletoOld, $contadores);
            if (!$ventaId) {
                continue;
            }

            $data = $this->mapeador->boleto($boletoOld, $servicioId, $clienteId, $ventaId, $asientoId, $recorridoId);
            $this->newConn->executeStatement(
                'INSERT INTO boleto ( servicio_id, recorrido_id, cliente_id, venta_id, asiento_id, created_at, legacy_id)
                 VALUES (:servicio_id, :recorrido_id, :cliente_id, :venta_id, :asiento_id, :created_at, :legacy_id)',
                $data
            );
            $contadores['boleto']++;
        }
    }

    private function crearVenta(?int $usuarioId, array $boletoOld, array &$contadores): ?int {
        if (!$usuarioId) {
            $usuarioId = 1;
        }

        $enclaveId = $boletoOld['estacion_creacion_id'] ?? $boletoOld['estacion_origen_id'] ?? null;
        if ($enclaveId) {
            $enclaveId = $this->getNewId('estacion', (string) $enclaveId) ?: (int) $enclaveId;
        }

        $insertId = $this->newConn->fetchOne(
            'INSERT INTO venta (usuario_id, enclave_id) VALUES (:usuario_id, :enclave_id) RETURNING id',
            [
                'usuario_id' => $usuarioId,
                'enclave_id' => $enclaveId,
            ]
        );
        $contadores['venta']++;

        return (int) $insertId;
    }

    private function fetchBoletosPorSalida(int $salidaId): array {
        return $this->fetchOld(
            'SELECT * FROM boleto WHERE salida_id = :salidaId ORDER BY id',
            ['salidaId' => $salidaId]
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
            'venta' => 0,
        ];
    }
}
