<?php

namespace App\Migration;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Migrates static (non-growing) entities from legacy FDN to the new system.
 *
 * Order matters: Empresa → Estacion → Bus → Asiento → Cliente → Usuario
 * Trayectos and Tarifas are handled separately due to their complex derivation logic.
 *
 * Static entities with numeric old PKs (empresa, estacion, asiento, cliente, usuario, tarifa)
 * reuse the old PK as the new id. Entities with string old PKs (bus, ruta→trayecto)
 * keep a legacy_id column.
 */
class MigradorEstaticos {
    public function __construct(
        private Connection $newConn,
        private \PDO $oldPdo,
        private Mapeador $mapeador,
    ) {
        $this->oldPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->oldPdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    public function migrar(?OutputInterface $output = null): array {
        $contadores = [
            'empresa' => 0,
            'estacion' => 0,
            'bus' => 0,
            'asiento' => 0,
            'cliente' => 0,
            'usuario' => 0,
            'trayecto' => 0,
            'tarifa' => 0,
            'piloto' => 0,
            'bus_marca' => 0,
            'localidad' => 0,
        ];

        $this->newConn->beginTransaction();
        try {
            $contadores['empresa'] = $this->migrarEmpresas($output);
            $contadores['piloto'] = $this->migrarPilotos($output);
            $contadores['localidad'] = $this->migrarLocalidades($output);
            $contadores['estacion'] = $this->migrarEstaciones($output);
            $contadores['cliente'] = $this->migrarClientes($output);
            $contadores['usuario'] = $this->migrarUsuarios($output);
            $contadores['bus_marca'] = $this->migrarMarcas($output);
            $contadores['bus'] = $this->migrarBuses($output);
            $contadores['asiento'] = $this->migrarAsientos($output);
            $contadores['trayecto'] = $this->migrarTrayectos($output);
            $contadores['tarifa'] = $this->migrarTarifas($output);



            $this->newConn->commit();
        } catch (\Throwable $e) {
            $this->newConn->rollBack();
            throw $e;
        }

        return $contadores;
    }

    // ─── Empresa ───────────────────────────────────────────────────

    private function migrarEmpresas(?OutputInterface $output = null): int {
        if ($output) $output->write('<info>Empresas...</info>');
        $rows = $this->fetchOld('SELECT * FROM empresa WHERE activo = 1');
        $count = 0;
        foreach ($rows as $row) {
            $lid = (string) $row['id'];
            if ($this->existe('empresa', $lid)) {
                continue;
            }
            $data = $this->mapeador->empresa($row);
            $this->newConn->executeStatement(
                'INSERT INTO empresa (id, nombre, nif, direccion, telefono, email) VALUES (:id, :nombre, :nif, :direccion, :telefono, :email)',
                $data
            );
            $count++;
        }

        if ($output) $output->writeln(" <info>{$count}</info>");
        return $count;
    }

    // ─── Estacion → Enclave ────────────────────────────────────────

    private function migrarEstaciones(?OutputInterface $output = null): int {
        if ($output) $output->write('<info>Estaciones...</info>');
        $rows = $this->fetchOld('SELECT * FROM estacion WHERE activo = 1');
        $count = 0;

        foreach ($rows as $row) {
            $lid = (string) $row['id'];
            if ($this->existe('enclave', $lid)) {
                continue;
            }
            $data = $this->mapeador->estacion($row);
            $this->newConn->executeStatement(
                "INSERT INTO enclave (id, tipo, nombre, direccion, latitud, longitud) VALUES (:id, 'estacion', :nombre, :direccion, :latitud, :longitud)",
                $data
            );
            $count++;
        }

        if ($output) $output->writeln(" <info>{$count}</info>");
        return $count;
    }

    // ─── Cliente ───────────────────────────────────────────────────

    private function migrarClientes(?OutputInterface $output = null): int {
        if ($output) $output->write('<info>Clientes...</info>');
        $rows = $this->fetchOld('SELECT TOP 100 * FROM cliente');
        $count = 0;

        foreach ($rows as $row) {
            $lid = (string) $row['id'];
            if ($this->existe('cliente', $lid)) {
                continue;
            }
            $data = $this->mapeador->cliente($row);
            $this->newConn->executeStatement(
                'INSERT INTO cliente (id, nombre, apellido, nit, email, telefono) VALUES (:id, :nombre, :apellido, :nit, :email, :telefono)',
                $data
            );
            $count++;
            if ($output && $count % 10000 === 0) {
                $output->write(sprintf("\r<info>Clientes... %d</info>", $count));
            }
        }

        if ($output) $output->writeln(" <info>{$count}</info>");
        return $count;
    }

    // ─── Usuario ───────────────────────────────────────────────────

    private function migrarUsuarios(?OutputInterface $output = null): int {
        if ($output) $output->write('<info>Usuarios...</info>');
        $rows = $this->fetchOld('SELECT * FROM custom_user');
        $count = 0;

        foreach ($rows as $row) {
            $lid = (string) $row['id'];
            if ($this->existe('usuario', $lid)) {
                continue;
            }
            $data = $this->mapeador->usuario($row);
            $fields = implode(', ', array_keys($data));
            $args = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));
            $this->newConn->executeStatement(
                "INSERT INTO usuario ({$fields}) VALUES ({$args})",
                $data
            );
            $count++;
        }

        if ($output) $output->writeln(" <info>{$count}</info>");
        return $count;
    }

    // ─── Bus ───────────────────────────────────────────────────────

    private function migrarBuses(?OutputInterface $output = null): int {
        if ($output) $output->write('<info>Buses...</info>');
        $rows = $this->fetchOld('SELECT b.*, bt.descripcion AS tipo_desc FROM bus b LEFT JOIN bus_tipo bt ON bt.id = b.tipo_id');
        $count = 0;

        foreach ($rows as $row) {
            if ($this->existe('bus', $row['codigo'])) {
                continue;
            }
            if (!$row['empresa_id']) {
                continue;
            }

            $empresaId = $this->resolveEmpresaId((int) $row['empresa_id']);
            if (!$empresaId) {
                continue;
            }

            $data = $this->mapeador->bus($row, $empresaId);
            $data['gama'] = isset($row['tipo_desc']) ? mb_substr($row['tipo_desc'], 0, 50) : null;

            // Prepare insert dynamically from mapped keys so we include additional fields
            $fields = implode(', ', array_keys($data));
            $args = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));

            $sql = "INSERT INTO bus ({$fields}) VALUES ({$args}) RETURNING id";
            $this->newConn->fetchOne($sql, $data);
            $count++;
        }

        if ($output) $output->writeln(" <info>{$count}</info>");
        return $count;
    }

    // ─── Asiento ───────────────────────────────────────────────────

    private function migrarAsientos(?OutputInterface $output = null): int {
        if ($output) $output->write('<info>Asientos...</info>');
        $rows = $this->fetchOld(
            'SELECT ba.*, b.codigo AS bus_codigo 
             FROM bus_asiento ba 
             JOIN bus_tipo bt ON bt.id = ba.tipoBus_id 
             JOIN bus b ON b.tipo_id = bt.id'
        );
        $count = 0;

        foreach ($rows as $row) {
            $lid = (string) $row['id'];
            if ($this->existe('asiento', $lid)) {
                continue;
            }

            $busId = $this->getBusIdByLegacy($row['bus_codigo']);
            if (!$busId) {
                continue;
            }

            $data = $this->mapeador->asiento($row, $busId);
            $this->newConn->executeStatement(
                'INSERT INTO asiento (id, numero, clase, fila, columna, bus_id) VALUES (:id, :numero, :clase, :fila, :columna, :bus_id)',
                $data
            );
            $count++;
        }

        if ($output) $output->writeln(" <info>{$count}</info>");
        return $count;
    }

    // ─── Trayecto (ruta + subtrayectos) ────────────────────────────

    private function migrarTrayectos(?OutputInterface $output = null): int {
        if ($output) $output->write('<info>Trayectos...</info>');
        $rutas = $this->fetchOldNoGenerator('SELECT * FROM ruta');
        $count = 0;

        foreach ($rutas as $ruta) {
            $rutaCodigo = $ruta['codigo'];
            if ($this->existe('trayecto', $rutaCodigo)) {
                continue;
            }

            $origenId = $this->resolveEstacionId((int) $ruta['estacion_origen_id']);
            $destinoId = $this->resolveEstacionId((int) $ruta['estacion_destino_id']);
            if (!$origenId || !$destinoId) {
                continue;
            }

            $data = $this->mapeador->trayecto($ruta, $origenId, $destinoId, true);
            $trayectoId = $this->newConn->fetchOne(
                'INSERT INTO trayecto (origen_id, destino_id, distancia_km, duracion_estimada_minutos, activo, es_ruta, legacy_id) VALUES (:origen_id, :destino_id, :distancia_km, :duracion_estimada_minutos, :activo, :es_ruta, :legacy_id) RETURNING id',
                $data
            );
            $count++;

            $subCount = $this->migrarSubTrayectos($rutaCodigo, (int) $trayectoId, $origenId, $destinoId);
            $count += $subCount;

            $allCount = $this->migrarTrayectosInversos($rutaCodigo, (int) $trayectoId, $origenId, $destinoId);
            $count += $allCount;
        }

        if ($output) $output->writeln(" <info>{$count}</info>");
        return $count;
    }

    private function migrarSubTrayectos(string $rutaCodigo, int $trayectoPadreId, int $rutaOrigenId, int $rutaDestinoId): int {
        $items = $this->fetchOld(
            'SELECT rei.*, e.nombre, e.id as estacion_id FROM ruta_estacion_item rei JOIN estacion e ON e.id = rei.estacion_id WHERE rei.ruta_codigo = :codigo ORDER BY rei.posicion ASC',
            ['codigo' => $rutaCodigo]
        );
        if (empty($items)) {
            return 0;
        }

        $estacionIds = [$rutaOrigenId];
        foreach ($items as $item) {
            $estId = $this->resolveEstacionId((int) $item['estacion_id']);
            if ($estId) {
                $estacionIds[] = $estId;
            }
        }
        $estacionIds[] = $rutaDestinoId;

        return $this->generarTrayectosDesdeEnclaves(
            $rutaCodigo,
            $trayectoPadreId,
            $estacionIds,
            false
        );
    }

    /**
     * Generates all forward sub-trayectos between every pair of enclaves
     * (A→B, A→C, ..., B→C, B→D, ...) for the given ordered enclave list.
     */
    private function generarTrayectosDesdeEnclaves(string $rutaCodigo, int $trayectoPadreId, array $estacionIds, bool $invertido): int {
        $n = count($estacionIds);
        $count = 0;

        for ($i = 0; $i < $n - 1; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $origen = $invertido ? $estacionIds[$j] : $estacionIds[$i];
                $destino = $invertido ? $estacionIds[$i] : $estacionIds[$j];
                $subLegacy = sprintf('%s-SUB-%d-%d', $rutaCodigo, $origen, $destino);

                if ($this->existe('trayecto', $subLegacy)) {
                    $subId = $this->getTrayectoIdByLegacy($subLegacy);
                    if ($subId) {
                        $this->linkTrayecto($trayectoPadreId, $subId);
                    }
                    continue;
                }

                $subId = $this->newConn->fetchOne(
                    'INSERT INTO trayecto (origen_id, destino_id, activo, es_ruta, legacy_id) VALUES (:origen_id, :destino_id, true, false, :legacy_id) RETURNING id',
                    [
                        'origen_id' => $origen,
                        'destino_id' => $destino,
                        'legacy_id' => $subLegacy,
                    ]
                );

                $this->linkTrayecto($trayectoPadreId, (int) $subId);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Generates reverse route (E→A) + all reverse sub-trayectos.
     */
    private function migrarTrayectosInversos(string $rutaCodigo, int $trayectoPadreId, int $rutaOrigenId, int $rutaDestinoId): int {
        $items = $this->fetchOld(
            'SELECT rei.*, e.id as estacion_id FROM ruta_estacion_item rei JOIN estacion e ON e.id = rei.estacion_id WHERE rei.ruta_codigo = :codigo ORDER BY rei.posicion ASC',
            ['codigo' => $rutaCodigo]
        );

        $estacionIds = [$rutaDestinoId];
        foreach ($items as $item) {
            $estId = $this->resolveEstacionId((int) $item['estacion_id']);
            if ($estId) {
                array_unshift($estacionIds, $estId);
            }
        }
        $estacionIds[] = $rutaOrigenId;
        $estacionIds = array_unique($estacionIds);

        $rutaInversaLegacy = sprintf('REV-%s', $rutaCodigo);

        if (!$this->existe('trayecto', $rutaInversaLegacy)) {
            $stmt = $this->oldPdo->prepare('SELECT * FROM ruta WHERE codigo = :codigo');
            $stmt->execute(['codigo' => $rutaCodigo]);
            $oldRuta = $stmt->fetch() ?: [];
            $data = $this->mapeador->trayecto(
                $oldRuta,
                $rutaDestinoId,
                $rutaOrigenId,
                true
            );
            $data['legacy_id'] = $rutaInversaLegacy;
            $data['distancia_km'] = $oldRuta['kilometros'] ?? null;

            $revId = $this->newConn->fetchOne(
                'INSERT INTO trayecto (origen_id, destino_id, distancia_km, duracion_estimada_minutos, activo, es_ruta, legacy_id) VALUES (:origen_id, :destino_id, :distancia_km, :duracion_estimada_minutos, :activo, :es_ruta, :legacy_id) RETURNING id',
                $data
            );
            $this->linkTrayecto($trayectoPadreId, (int) $revId);

            return 1 + $this->generarTrayectosDesdeEnclaves($rutaCodigo, (int) $revId, $estacionIds, true);
        }

        return 0;
    }

    private function linkTrayecto(int $padreId, int $hijoId): void {
        $this->newConn->executeStatement(
            'INSERT INTO trayecto_trayecto (trayecto_source, trayecto_target) VALUES (:padre, :hijo) ON CONFLICT DO NOTHING',
            ['padre' => $padreId, 'hijo' => $hijoId]
        );
    }

    // ─── Tarifa ────────────────────────────────────────────────────

    private function migrarTarifas(?OutputInterface $output = null): int {
        if ($output) $output->write('<info>Tarifas...</info>');
        $rows = $this->fetchOld('SELECT tb.*, e.id AS empresa_id FROM tarifas_boleto tb CROSS JOIN (SELECT MIN(id) AS id FROM empresa WHERE activo = 1) e');
        $count = 0;

        foreach ($rows as $row) {
            $lid = (string) $row['id'];
            if ($this->existe('tarifa', $lid)) {
                continue;
            }

            $empresaId = $this->resolveEmpresaId((int) ($row['empresa_id'] ?? 0));
            if (!$empresaId) {
                $empresaId = $this->getFirstEmpresaId();
                if (!$empresaId) {
                    continue;
                }
            }

            $data = $this->mapeador->tarifa($row, $empresaId);
            $this->newConn->executeStatement(
                'INSERT INTO tarifa (id, nombre, precio_clase_a, precio_clase_b, empresa_id, bus_id) VALUES (:id, :nombre, :precio_clase_a, :precio_clase_b, :empresa_id, :bus_id)',
                $data
            );
            $count++;
        }

        if ($output) $output->writeln(" <info>{$count}</info>");
        return $count;
    }

    // ─── Piloto ────────────────────────────────────────────────────

    private function migrarPilotos(?OutputInterface $output = null): int {
        if ($output) $output->write('<info>Pilotos...</info>');
        $rows = $this->fetchOld('SELECT * FROM piloto');
        $count = 0;

        foreach ($rows as $row) {
            $lid = (string) $row['id'];
            if ($this->existe('piloto', $lid)) {
                continue;
            }
            $data = $this->mapeador->piloto($row);
            $fields = implode(', ', array_keys($data));
            $args = implode(', ', array_map(fn($k) => ":{$k}", array_keys($data)));
            $this->newConn->executeStatement(
                "INSERT INTO piloto ({$fields}) VALUES ({$args})",
                $data
            );
            $count++;
        }

        if ($output) $output->writeln(" <info>{$count}</info>");
        return $count;
    }

    // ─── BusMarca ──────────────────────────────────────────────────

    private function migrarMarcas(?OutputInterface $output = null): int {
        if ($output) $output->write('<info>Marcas de bus...</info>');
        $rows = $this->fetchOld('SELECT * FROM bus_marca');
        $count = 0;

        foreach ($rows as $row) {
            $lid = (string) $row['id'];
            if ($this->existe('bus_marca', $lid)) {
                continue;
            }

            $data = [
                'id' => (int) $row['id'],
                'nombre' => mb_substr($row['nombre'] ?? '', 0, 20),
            ];

            $this->newConn->executeStatement(
                'INSERT INTO bus_marca (id, nombre) VALUES (:id, :nombre)',
                $data
            );
            $count++;
        }

        if ($output) $output->writeln(" <info>{$count}</info>");
        return $count;
    }

    // ─── Localidad ─────────────────────────────────────────────────

    private function migrarLocalidades(?OutputInterface $output = null): int {
        if ($output) $output->write('<info>Localidades...</info>');
        try {
            $rows = $this->fetchOld('SELECT * FROM departamento');
        } catch (\Throwable $e) {
            if ($output) $output->writeln(' <comment>tabla no encontrada, saltando</comment>');
            return 0;
        }
        $count = 0;

        try {
            foreach ($rows as $row) {
                $lid = (string) $row['id'];
                if ($this->existe('localidad', $lid)) {
                    continue;
                }

                $data = [
                    'id' => (int) $row['id'],
                    'nombre' => mb_substr($row['nombre'] ?? '', 0, 255),
                ];

                $this->newConn->executeStatement(
                    "INSERT INTO localidad (id, nombre) VALUES (:id, :nombre)",
                    $data
                );
                $count++;
            }
        } catch (\Throwable $e) {
            if ($output) $output->writeln(' <comment>tabla no encontrada, saltando</comment>');
            return 0;
        }

        if ($output) $output->writeln(" <info>{$count}</info>");
        return $count;
    }

    // ─── Helpers ───────────────────────────────────────────────────

    private function existe(string $tabla, string $legacyId): bool {
        $sql = match ($tabla) {
            'empresa', 'enclave', 'asiento', 'cliente', 'usuario', 'tarifa', 'piloto', 'localidad', 'bus_marca' => "SELECT 1 FROM {$tabla} WHERE id = :lid",
            'bus' => "SELECT 1 FROM {$tabla} WHERE codigo = :lid",
            'trayecto' => "SELECT 1 FROM {$tabla} WHERE legacy_id = :lid",
            default => "SELECT 1 FROM {$tabla} WHERE legacy_id = :lid",
        };
        return $this->newConn->fetchOne($sql, ['lid' => $legacyId]) !== false;
    }

    private function resolveEmpresaId(int $oldId): ?int {
        return $this->newConn->fetchOne('SELECT id FROM empresa WHERE id = :id', ['id' => $oldId]) ? (int) $oldId : null;
    }

    private function resolveEstacionId(int $oldId): ?int {
        return $this->newConn->fetchOne('SELECT id FROM enclave WHERE id = :id', ['id' => $oldId]) ? (int) $oldId : null;
    }

    private function getBusIdByLegacy(string $codigo): ?int {
        $id = $this->newConn->fetchOne('SELECT id FROM bus WHERE codigo = :lid', ['lid' => $codigo]);
        return $id !== false ? (int) $id : null;
    }

    private function getTrayectoIdByLegacy(string $legacyId): ?int {
        $id = $this->newConn->fetchOne('SELECT id FROM trayecto WHERE legacy_id = :lid', ['lid' => $legacyId]);
        return $id !== false ? (int) $id : null;
    }

    private function getFirstEmpresaId(): ?int {
        $id = $this->newConn->fetchOne('SELECT id FROM empresa ORDER BY id ASC LIMIT 1');
        return $id !== false ? (int) $id : null;
    }

    private function fetchOld(string $sql, array $params = []): \Generator {
        $stmt = $this->oldPdo->prepare($sql);
        $stmt->execute($params);
        while ($row = $stmt->fetch()) {
            yield $row;
        }
    }

    private function fetchOldNoGenerator(string $sql, array $params = []) {
        $stmt = $this->oldPdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
