<?php

namespace App\Migration;

use App\Migration\Job\Progreso;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Migrates static (non-growing) entities from legacy FDN to the new system.
 *
 * Order matters: Empresa → Estacion → Bus → Asiento/Señal → Cliente → Usuario
 * Trayectos and Tarifas are handled separately due to their complex derivation logic.
 *
 * Static entities with numeric old PKs (empresa, estacion, cliente, usuario, tarifa)
 * reuse the old PK as the new id. Entities with string old PKs (bus, ruta→trayecto)
 * keep a legacy_id column. Asientos y señales del croquis son del tipo de bus en
 * el legado: se copian a cada bus con id nuevo.
 */
class MigradorEstaticos
{
    public function __construct(
        private Connection $newConn,
        #[Target("oldPdo")] private \PDO $oldPdo,
        #[
            Target("doctrine.orm.systemfdn_entity_manager"),
        ]
        private EntityManagerInterface $systemfdnEm,
        private Mapeador $mapeador,
    ) {
        $this->oldPdo->setAttribute(
            \PDO::ATTR_ERRMODE,
            \PDO::ERRMODE_EXCEPTION,
        );
        $this->oldPdo->setAttribute(
            \PDO::ATTR_DEFAULT_FETCH_MODE,
            \PDO::FETCH_ASSOC,
        );
    }

    public function migrar(
        ?OutputInterface $output = null,
        $entities = [],
        ?Progreso $progreso = null,
    ): array {
        $contadores = [
            "empresa" => 0,
            "estacion" => 0,
            "bus" => 0,
            "asiento" => 0,
            "senal" => 0,
            "cliente" => 0,
            "usuario" => 0,
            "trayecto" => 0,
            "tarifa" => 0,
            "piloto" => 0,
            "bus_marca" => 0,
            "localidad" => 0,
        ];

        $this->newConn->beginTransaction();
        try {
            if (!empty($entities)) {
                if ($output) {
                    $output->writeln(
                        "<comment>Migrando solo entidades: " .
                            implode(", ", $entities) .
                            "</comment>",
                    );
                }
                foreach ($entities as $value) {
                    if ($progreso && $progreso->debeCancelar()) {
                        break;
                    }
                    if (!isset(self::ENTIDAD_A_METODO[$value])) {
                        throw new \InvalidArgumentException(
                            "Entidad estática desconocida: {$value}",
                        );
                    }
                    $funcName = self::ENTIDAD_A_METODO[$value];
                    $contadores[$value] = $this->$funcName($output);
                }
            } else {
                $contadores["empresa"] = $this->migrarEmpresas($output);
                $contadores["piloto"] = $this->migrarPilotos($output);
                $contadores["localidad"] = $this->migrarLocalidads($output);
                $contadores["estacion"] = $this->migrarEstacions($output);
                $contadores["cliente"] = $this->migrarClientes($output);
                $contadores["usuario"] = $this->migrarUsuarios($output);
                $contadores["bus_marca"] = $this->migrarMarcas($output);
                $contadores["bus"] = $this->migrarBuss($output);
                $contadores["asiento"] = $this->migrarAsientos($output);
                $contadores["senal"] = $this->migrarSenales($output);
                $contadores["trayecto"] = $this->migrarTrayectos($output);
                $contadores["tarifa"] = $this->migrarTarifas($output);
            }
            $this->newConn->commit();
        } catch (\Throwable $e) {
            $this->newConn->rollBack();
            throw $e;
        }

        return $contadores;
    }

    /**
     * Nombre canónico de entidad → método migrador.
     * Evita el patrón "migrar".ucfirst($x)."s" que rompe con bus_marca.
     */
    private const ENTIDAD_A_METODO = [
        "empresa" => "migrarEmpresas",
        "estacion" => "migrarEstacions",
        "cliente" => "migrarClientes",
        "usuario" => "migrarUsuarios",
        "bus" => "migrarBuss",
        "asiento" => "migrarAsientos",
        "senal" => "migrarSenales",
        "trayecto" => "migrarTrayectos",
        "tarifa" => "migrarTarifas",
        "piloto" => "migrarPilotos",
        "marca" => "migrarMarcas",
        "localidad" => "migrarLocalidads",
    ];

    /**
     * Migra una única entidad estática.
     *
     * @return array<string, int> contadores con clave = nombre de entidad
     */
    public function migrarEntidad(
        string $nombre,
        ?OutputInterface $output = null,
        ?Progreso $progreso = null,
        ?int $limiteClientes = null,
    ): array {
        if (!isset(self::ENTIDAD_A_METODO[$nombre])) {
            throw new \InvalidArgumentException(
                "Entidad estática desconocida: {$nombre}",
            );
        }

        // Clientes es la única tabla grande: el lote controlado respeta la
        // cantidad pedida y su propia transacción (fuera del bucle de migrar()).
        if ("cliente" === $nombre && null !== $limiteClientes) {
            $count = $this->migrarClienteLote(
                $output,
                $limiteClientes,
                $progreso,
            );

            return ["cliente" => $count];
        }

        return $this->migrar($output, [$nombre], $progreso);
    }

    /**
     * Lote controlado de clientes (TOP {limite} ORDER BY id) en su propia transacción.
     */
    public function migrarClienteLote(
        ?OutputInterface $output = null,
        int $limite = 1000,
        ?Progreso $progreso = null,
    ): int {
        $this->newConn->beginTransaction();
        try {
            $count = $this->migrarClientes($output, $limite, $progreso);
            $this->newConn->commit();
        } catch (\Throwable $e) {
            $this->newConn->rollBack();
            throw $e;
        }

        return $count;
    }

    // ─── Empresa ───────────────────────────────────────────────────

    private function migrarEmpresas(?OutputInterface $output = null): int
    {
        if ($output) {
            $output->write("<info>Empresas...</info>");
        }
        $rows = $this->fetchOld("SELECT * FROM empresa WHERE activo = 1");
        $count = 0;
        foreach ($rows as $row) {
            $lid = (string) $row["id"];
            if ($this->existe("empresa", $lid)) {
                continue;
            }
            $data = $this->mapeador->empresa($row);
            $this->newConn->executeStatement(
                "INSERT INTO empresa (id, nombre, nit, direccion, telefono, email) VALUES (:id, :nombre, :nit, :direccion, :telefono, :email)",
                $data,
            );
            $count++;
        }

        if ($output) {
            $output->writeln(" <info>{$count}</info>");
        }
        return $count;
    }

    // ─── Estacion → Enclave ────────────────────────────────────────

    private function migrarEstacions(?OutputInterface $output = null): int
    {
        if ($output) {
            $output->write("<info>Estaciones...</info>");
        }
        $rows = $this->fetchOld("SELECT * FROM estacion WHERE activo = 1");
        $count = 0;

        foreach ($rows as $row) {
            $lid = (string) $row["id"];
            if ($this->existe("enclave", $lid)) {
                continue;
            }
            $data = $this->mapeador->estacion($row);
            $this->newConn->executeStatement(
                "INSERT INTO enclave (id, tipo, nombre, direccion, latitud, longitud) VALUES (:id, 'estacion', :nombre, :direccion, :latitud, :longitud)",
                $data,
            );
            $count++;
        }

        if ($output) {
            $output->writeln(" <info>{$count}</info>");
        }
        return $count;
    }

    // ─── Cliente ───────────────────────────────────────────────────

    private function migrarClientes(
        ?OutputInterface $output = null,
        ?int $limite = null,
        ?Progreso $progreso = null,
    ): int {
        if ($output) {
            $output->write("<info>Clientes...</info>");
        }
        $top = $limite ?? 1000;
        $rows = $this->fetchOld("SELECT TOP {$top} * FROM cliente ORDER BY id");
        $count = 0;

        foreach ($rows as $row) {
            if ($progreso && $progreso->debeCancelar()) {
                break;
            }
            $lid = (string) $row["id"];
            if ($this->existe("cliente", $lid)) {
                continue;
            }
            $data = $this->mapeador->cliente($row);
            $this->newConn->executeStatement(
                "INSERT INTO cliente (id, nombre, apellido, nit, email, telefono) VALUES (:id, :nombre, :apellido, :nit, :email, :telefono)",
                $data,
            );
            $count++;
            if ($output && $count % 10000 === 0) {
                $output->write(
                    sprintf("\r<info>Clientes... %d</info>", $count),
                );
            }
        }

        if ($output) {
            $output->writeln(" <info>{$count}</info>");
        }
        return $count;
    }

    // ─── Usuario ───────────────────────────────────────────────────

    private function migrarUsuarios(?OutputInterface $output = null): int
    {
        if ($output) {
            $output->write("<info>Usuarios...</info>");
        }
        $rows = $this->fetchOld("SELECT * FROM custom_user");
        $count = 0;
        $date = new DateTime()->format("Y-m-d H:i:s");
        foreach ($rows as $row) {
            $lid = (string) $row["id"];
            if ($this->existe("usuario", $lid)) {
                continue;
            }
            $data = $this->mapeador->usuario($row);
            $fields = implode(", ", array_keys($data));
            $args = implode(
                ", ",
                array_map(fn($k) => ":{$k}", array_keys($data)),
            );
            $id = $this->newConn->executeStatement(
                "INSERT INTO usuario ({$fields}) VALUES ({$args})",
                $data,
            );
            $token = "fdn_" . bin2hex(random_bytes(32));
            $data = [
                "expira" => null,
                "token" => $token,
                "activo" => 1,
                "created_at" => $date,
                "updated_at" => $date,
                "usuario_id" => $id,
            ];
            $fields = implode(", ", array_keys($data));
            $args = implode(
                ", ",
                array_map(fn($k) => ":{$k}", array_keys($data)),
            );
            $this->newConn->executeStatement(
                "INSERT INTO api_token  ({$fields}) VALUES ({$args})",
                $data,
            );
            $count++;
        }

        if ($output) {
            $output->writeln(" <info>{$count}</info>");
        }
        return $count;
    }

    // ─── Bus ───────────────────────────────────────────────────────

    private function migrarBuss(?OutputInterface $output = null): int
    {
        if ($output) {
            $output->write("<info>Buses...</info>");
        }
        $rows = $this->fetchOld(
            "SELECT b.*, bt.descripcion AS tipo_desc FROM bus b LEFT JOIN bus_tipo bt ON bt.id = b.tipo_id",
        );
        $count = 0;

        foreach ($rows as $row) {
            if ($this->existe("bus", $row["codigo"])) {
                continue;
            }
            if (!$row["empresa_id"]) {
                continue;
            }

            $empresaId = $this->resolveEmpresaId((int) $row["empresa_id"]);
            if (!$empresaId) {
                continue;
            }

            $data = $this->mapeador->bus($row, $empresaId);
            $data["gama"] = isset($row["tipo_desc"])
                ? mb_substr($row["tipo_desc"], 0, 50)
                : null;

            // Prepare insert dynamically from mapped keys so we include additional fields
            $fields = implode(", ", array_keys($data));
            $args = implode(
                ", ",
                array_map(fn($k) => ":{$k}", array_keys($data)),
            );

            $sql = "INSERT INTO bus ({$fields}) VALUES ({$args}) RETURNING id";
            $this->newConn->fetchOne($sql, $data);
            $count++;
        }

        if ($output) {
            $output->writeln(" <info>{$count}</info>");
        }
        return $count;
    }

    // ─── Asiento ───────────────────────────────────────────────────

    /**
     * Los asientos del legado son del tipo de bus: cada bus de ese tipo recibe
     * su copia. Ya migrado = existe `(bus_id, numero)`.
     */
    private function migrarAsientos(?OutputInterface $output = null): int
    {
        if ($output) {
            $output->write("<info>Asientos...</info>");
        }
        $rows = $this->fetchOld(
            'SELECT ba.*, b.codigo AS bus_codigo
             FROM bus_asiento ba
             JOIN bus b ON b.tipo_id = ba.tipoBus_id',
        );
        $existentes = $this->clavesExistentes(
            "SELECT bus_id, numero FROM asiento",
        );
        $count = 0;

        foreach ($rows as $row) {
            $busId = $this->getBusIdByLegacy($row["bus_codigo"]);
            if (!$busId) {
                continue;
            }

            $data = $this->mapeador->asiento($row, $busId);
            $clave = "{$busId}:{$data["numero"]}";
            if (isset($existentes[$clave])) {
                continue;
            }
            $this->newConn->executeStatement(
                "INSERT INTO asiento (numero, clase, planta, fila, columna, bus_id) VALUES (:numero, :clase, :planta, :fila, :columna, :bus_id)",
                $data,
            );
            $existentes[$clave] = true;
            $count++;
        }

        if ($output) {
            $output->writeln(" <info>{$count}</info>");
        }
        return $count;
    }

    // ─── Señales del croquis (chofer, puertas) ─────────────────────

    /**
     * `bus_senal` es, como los asientos, del tipo de bus: cada bus recibe su
     * copia. Ya migrada = existe `(bus_id, planta, fila, columna)`. Los tipos
     * que no son chofer ni puerta se omiten.
     */
    private function migrarSenales(?OutputInterface $output = null): int
    {
        if ($output) {
            $output->write("<info>Chofer y puertas...</info>");
        }
        $rows = $this->fetchOld(
            'SELECT s.*, t.nombre AS tipo_nombre, b.codigo AS bus_codigo
             FROM bus_senal s
             JOIN bus_senal_tipo t ON t.id = s.tipo_id
             JOIN bus b ON b.tipo_id = s.tipoBus_id',
        );
        $existentes = $this->clavesExistentes(
            "SELECT bus_id, planta, fila, columna FROM bus_senal",
        );
        $count = 0;

        foreach ($rows as $row) {
            $busId = $this->getBusIdByLegacy($row["bus_codigo"]);
            if (!$busId) {
                continue;
            }

            $data = $this->mapeador->senal($row, $busId);
            if ($data === null) {
                continue;
            }
            $clave = "{$busId}:{$data["planta"]}:{$data["fila"]}:{$data["columna"]}";
            if (isset($existentes[$clave])) {
                continue;
            }
            $this->newConn->executeStatement(
                "INSERT INTO bus_senal (tipo, planta, fila, columna, bus_id) VALUES (:tipo, :planta, :fila, :columna, :bus_id)",
                $data,
            );
            $existentes[$clave] = true;
            $count++;
        }

        if ($output) {
            $output->writeln(" <info>{$count}</info>");
        }
        return $count;
    }

    /**
     * Claves `col1:col2:…` de las filas de `$sql` (dedupe en memoria).
     *
     * @return array<string, true>
     */
    private function clavesExistentes(string $sql): array
    {
        $claves = [];
        foreach ($this->newConn->fetchAllNumeric($sql) as $fila) {
            $claves[implode(":", $fila)] = true;
        }

        return $claves;
    }

    // ─── Trayecto (ruta + subtrayectos) ────────────────────────────

    private function migrarTrayectos(?OutputInterface $output = null): int
    {
        if ($output) {
            $output->write("<info>Trayectos...</info>");
        }
        $rutas = $this->fetchOldNoGenerator("SELECT * FROM ruta");
        $count = 0;

        foreach ($rutas as $ruta) {
            if (empty($ruta["codigo"])) {
                continue;
            }
            $rutaCodigo = $ruta["codigo"];
            if ($this->existe("trayecto", $rutaCodigo)) {
                continue;
            }

            $origenId = $this->resolveEstacionId(
                (int) $ruta["estacion_origen_id"],
            );
            $destinoId = $this->resolveEstacionId(
                (int) $ruta["estacion_destino_id"],
            );
            if (!$origenId || !$destinoId) {
                continue;
            }

            $data = $this->mapeador->trayecto(
                $ruta,
                $origenId,
                $destinoId,
                true,
            );

            if (
                $this->existByColumns("trayecto", [
                    "origen_id" => $data["origen_id"],
                    "destino_id" => $data["destino_id"],
                ])
            ) {
                continue;
            }

            $trayectoId = $this->newConn->fetchOne(
                "INSERT INTO trayecto (origen_id, destino_id, distancia_km, duracion_estimada_minutos, activo, legacy_id) VALUES (:origen_id, :destino_id, :distancia_km, :duracion_estimada_minutos, :activo,  :legacy_id) RETURNING id",
                $data,
            );
            $count++;

            $subCount = $this->migrarSubTrayectos(
                $rutaCodigo,
                (int) $trayectoId,
                $origenId,
                $destinoId,
            );
            $count += $subCount;

            $allCount = $this->migrarTrayectosInversos(
                $rutaCodigo,
                (int) $trayectoId,
                $origenId,
                $destinoId,
            );
            $count += $allCount;
        }

        if ($output) {
            $output->writeln(" <info>{$count}</info>");
        }
        return $count;
    }

    private function migrarSubTrayectos(
        string $rutaCodigo,
        int $trayectoPadreId,
        int $rutaOrigenId,
        int $rutaDestinoId,
    ): int {
        $items = $this->fetchOld(
            "SELECT rei.*, e.nombre, e.id as estacion_id FROM ruta_estacion_item rei JOIN estacion e ON e.id = rei.estacion_id WHERE rei.ruta_codigo = :codigo ORDER BY rei.posicion ASC",
            ["codigo" => $rutaCodigo],
        );
        if (empty($items)) {
            return 0;
        }

        $estacionIds = [$rutaOrigenId];
        foreach ($items as $item) {
            $estId = $this->resolveEstacionId((int) $item["estacion_id"]);
            if ($estId) {
                $estacionIds[] = $estId;
            }
        }
        $estacionIds[] = $rutaDestinoId;

        return $this->generarTrayectosDesdeEnclaves(
            $rutaCodigo,
            $trayectoPadreId,
            $estacionIds,
            false,
        );
    }

    /**
     * Generates all forward sub-trayectos between every pair of enclaves
     * (A→B, A→C, ..., B→C, B→D, ...) for the given ordered enclave list.
     */
    private function generarTrayectosDesdeEnclaves(
        string $rutaCodigo,
        int $trayectoPadreId,
        array $estacionIds,
        bool $invertido,
    ): int {
        $n = count($estacionIds);
        $count = 0;

        for ($i = 0; $i < $n - 1; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $origen = $invertido ? $estacionIds[$j] : $estacionIds[$i];
                $destino = $invertido ? $estacionIds[$i] : $estacionIds[$j];
                $subLegacy = sprintf(
                    "%s-SUB-%d-%d",
                    $rutaCodigo,
                    $origen,
                    $destino,
                );

                if ($this->existe("trayecto", $subLegacy)) {
                    $subId = $this->getTrayectoIdByLegacy($subLegacy);
                    if ($subId) {
                        $this->linkTrayecto($trayectoPadreId, $subId);
                    }
                    continue;
                }
                if (
                    $this->existByColumns("trayecto", [
                        "origen_id" => $origen,
                        "destino_id" => $destino,
                    ])
                ) {
                    continue;
                }

                $subId = $this->newConn->fetchOne(
                    "INSERT INTO trayecto (origen_id, destino_id, activo,  legacy_id) VALUES (:origen_id, :destino_id, true, :legacy_id) RETURNING id",
                    [
                        "origen_id" => $origen,
                        "destino_id" => $destino,
                        "legacy_id" => $subLegacy,
                    ],
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
    private function migrarTrayectosInversos(
        string $rutaCodigo,
        int $trayectoPadreId,
        int $rutaOrigenId,
        int $rutaDestinoId,
    ): int {
        $items = $this->fetchOld(
            "SELECT rei.*, e.id as estacion_id FROM ruta_estacion_item rei JOIN estacion e ON e.id = rei.estacion_id WHERE rei.ruta_codigo = :codigo ORDER BY rei.posicion ASC",
            ["codigo" => $rutaCodigo],
        );

        $estacionIds = [$rutaDestinoId];
        foreach ($items as $item) {
            $estId = $this->resolveEstacionId((int) $item["estacion_id"]);
            if ($estId) {
                array_unshift($estacionIds, $estId);
            }
        }
        $estacionIds[] = $rutaOrigenId;
        $estacionIds = array_unique($estacionIds);

        $rutaInversaLegacy = sprintf("REV-%s", $rutaCodigo);
        if (!$this->existe("trayecto", $rutaInversaLegacy)) {
            $stmt = $this->oldPdo->prepare(
                "SELECT * FROM ruta WHERE codigo = :codigo",
            );
            $stmt->execute(["codigo" => $rutaCodigo]);
            $oldRuta = $stmt->fetch();
            if (!$oldRuta) {
                return 0;
            }
            $oldRuta = $this->sanitizeUtf8($oldRuta);
            $data = $this->mapeador->trayecto(
                $oldRuta,
                $rutaDestinoId,
                $rutaOrigenId,
                true,
            );
            $data["legacy_id"] = $rutaInversaLegacy;
            $data["distancia_km"] = $oldRuta["kilometros"] ?? null;

            if (
                $this->existByColumns("trayecto", [
                    "origen_id" => $data["origen_id"],
                    "destino_id" => $data["destino_id"],
                ])
            ) {
                return 0;
            }

            $revId = $this->newConn->fetchOne(
                "INSERT INTO trayecto (origen_id, destino_id, distancia_km, duracion_estimada_minutos, activo,  legacy_id) VALUES (:origen_id, :destino_id, :distancia_km, :duracion_estimada_minutos, :activo, :legacy_id) RETURNING id",
                $data,
            );
            $this->linkTrayecto($trayectoPadreId, (int) $revId);

            return 1 +
                $this->generarTrayectosDesdeEnclaves(
                    $rutaCodigo,
                    (int) $revId,
                    $estacionIds,
                    true,
                );
        }

        return 0;
    }

    private function linkTrayecto(int $padreId, int $hijoId): void
    {
        $exists = $this->newConn->fetchOne(
            "SELECT id FROM subtrayecto WHERE below_to_id = :padre AND trayecto_id = :hijo",
            ["padre" => $padreId, "hijo" => $hijoId],
        );
        if ($exists) {
            return;
        }

        $this->newConn->executeStatement(
            "INSERT INTO subtrayecto (trayecto_id, below_to_id, activo) VALUES (:hijo, :padre, true)",
            ["hijo" => $hijoId, "padre" => $padreId],
        );
    }

    // ─── Tarifa ────────────────────────────────────────────────────

    private function migrarTarifas(?OutputInterface $output = null): int
    {
        if ($output) {
            $output->write("<info>Tarifas...</info>");
        }
        $rows = $this->fetchOld(
            "SELECT tb.* FROM tarifas_boleto tb ORDER BY tb.id DESC",
        );

        $count = 0;
        $defaultUsuarioId = $this->getFirstUsuarioId();

        foreach ($rows as $row) {
            $lid = (string) $row["id"];
            if ($this->existe("boleto_tarifa", $lid)) {
                continue;
            }

            $empresaId = $this->getFirstEmpresaId();
            if (!$empresaId) {
                continue;
            }

            $clase = ((int) ($row["clase_asiento"] ?? 0)) === 2 ? "B" : "A";
            $trayectoId = $this->findTrayectoIdPorTarifa($row);
            $usuarioId = $defaultUsuarioId ?? 1;

            $data = $this->mapeador->boletoTarifa(
                $row,
                $empresaId,
                $clase,
                $usuarioId,
                $trayectoId,
            );
            $this->newConn->executeStatement(
                'INSERT INTO boleto_tarifa (id, nombre, precio_monto, precio_moneda, clase, empresa_id, bus_id, trayecto_id, usuario_id)
                 VALUES (:id, :nombre, :precio_monto, :precio_moneda, :clase, :empresa_id, :bus_id, :trayecto_id, :usuario_id)',
                $data,
            );
            $count++;
        }

        if ($output) {
            $output->writeln(" <info>{$count}</info>");
        }
        return $count;
    }

    private function getFirstUsuarioId(): ?int
    {
        $id = $this->newConn->fetchOne(
            "SELECT id FROM usuario ORDER BY id ASC LIMIT 1",
        );
        return $id !== false ? (int) $id : null;
    }

    /**
     * Busca un trayecto cuyo origen/destino coincidan con la tarifa legacy
     * (las estaciones reutilizan su id legacy como id de enclave).
     */
    private function findTrayectoIdPorTarifa(array $row): ?int
    {
        $origen = $this->resolveEstacionId(
            (int) ($row["estacion_origen_id"] ?? 0),
        );
        $destino = $this->resolveEstacionId(
            (int) ($row["estacion_destino_id"] ?? 0),
        );
        if (!$origen || !$destino) {
            return null;
        }

        $id = $this->newConn->fetchOne(
            "SELECT id FROM trayecto WHERE origen_id = :origen AND destino_id = :destino ORDER BY id LIMIT 1",
            ["origen" => $origen, "destino" => $destino],
        );
        return $id !== false ? (int) $id : null;
    }

    // ─── Piloto ────────────────────────────────────────────────────

    private function migrarPilotos(?OutputInterface $output = null): int
    {
        if ($output) {
            $output->write("<info>Pilotos...</info>");
        }
        $rows = $this->fetchOld("SELECT * FROM piloto");
        $count = 0;

        foreach ($rows as $row) {
            $lid = (string) $row["id"];
            if ($this->existe("piloto", $lid)) {
                continue;
            }
            $data = $this->mapeador->piloto($row);
            $fields = implode(", ", array_keys($data));
            $args = implode(
                ", ",
                array_map(fn($k) => ":{$k}", array_keys($data)),
            );
            $this->newConn->executeStatement(
                "INSERT INTO piloto ({$fields}) VALUES ({$args})",
                $data,
            );
            $count++;
        }

        if ($output) {
            $output->writeln(" <info>{$count}</info>");
        }
        return $count;
    }

    // ─── BusMarca ──────────────────────────────────────────────────

    private function migrarMarcas(?OutputInterface $output = null): int
    {
        if ($output) {
            $output->write("<info>Marcas de bus...</info>");
        }
        $rows = $this->fetchOld("SELECT * FROM bus_marca");
        $count = 0;

        foreach ($rows as $row) {
            $lid = (string) $row["id"];
            if ($this->existe("bus_marca", $lid)) {
                continue;
            }

            $data = [
                "id" => (int) $row["id"],
                "nombre" => mb_substr($row["nombre"] ?? "", 0, 20),
            ];

            $this->newConn->executeStatement(
                "INSERT INTO bus_marca (id, nombre) VALUES (:id, :nombre)",
                $data,
            );
            $count++;
        }

        if ($output) {
            $output->writeln(" <info>{$count}</info>");
        }
        return $count;
    }

    // ─── Localidad ─────────────────────────────────────────────────

    private function migrarLocalidads(?OutputInterface $output = null): int
    {
        if ($output) {
            $output->write("<info>Localidades...</info>");
        }
        try {
            $rows = $this->fetchOld("SELECT * FROM departamento");
        } catch (\Throwable $e) {
            if ($output) {
                $output->writeln(
                    " <comment>tabla no encontrada, saltando</comment>",
                );
            }
            return 0;
        }
        $count = 0;

        try {
            foreach ($rows as $row) {
                $lid = (string) $row["id"];
                if ($this->existe("localidad", $lid)) {
                    continue;
                }

                $data = [
                    "id" => (int) $row["id"],
                    "nombre" => mb_substr($row["nombre"] ?? "", 0, 255),
                ];

                $this->newConn->executeStatement(
                    "INSERT INTO localidad (id, nombre) VALUES (:id, :nombre)",
                    $data,
                );
                $count++;
            }
        } catch (\Throwable $e) {
            if ($output) {
                $output->writeln(
                    " <comment>tabla no encontrada, saltando</comment>",
                );
            }
            return 0;
        }

        if ($output) {
            $output->writeln(" <info>{$count}</info>");
        }
        return $count;
    }

    // ─── Helpers ───────────────────────────────────────────────────

    private function existe(string $tabla, string $legacyId): bool
    {
        $sql = match ($tabla) {
            "empresa",
            "enclave",
            "asiento",
            "cliente",
            "usuario",
            "boleto_tarifa",
            "piloto",
            "localidad",
            "bus_marca"
                => "SELECT 1 FROM {$tabla} WHERE id = :lid",
            "bus" => "SELECT 1 FROM {$tabla} WHERE codigo = :lid",
            "trayecto" => "SELECT 1 FROM {$tabla} WHERE legacy_id = :lid",
            default => "SELECT 1 FROM {$tabla} WHERE legacy_id = :lid",
        };
        return $this->newConn->fetchOne($sql, ["lid" => $legacyId]) !== false;
    }

    private function existByColumns(string $tabla, array $cols): bool
    {
        $sql = "SELECT 1 FROM {$tabla} WHERE ";
        $i = 0;
        $j = count($cols);
        foreach ($cols as $key => $value) {
            $sql .= "{$key} = {$value}";
            if (++$i < $j) {
                $sql .= " AND ";
            }
        }
        return $this->newConn->fetchOne($sql) !== false;
    }

    private function resolveEmpresaId(int $oldId): ?int
    {
        return $this->newConn->fetchOne(
            "SELECT id FROM empresa WHERE id = :id",
            ["id" => $oldId],
        )
            ? (int) $oldId
            : null;
    }

    private function resolveEstacionId(int $oldId): ?int
    {
        return $this->newConn->fetchOne(
            "SELECT id FROM enclave WHERE id = :id",
            ["id" => $oldId],
        )
            ? (int) $oldId
            : null;
    }

    private function getBusIdByLegacy(string $codigo): ?int
    {
        $id = $this->newConn->fetchOne(
            "SELECT id FROM bus WHERE codigo = :lid",
            ["lid" => $codigo],
        );
        return $id !== false ? (int) $id : null;
    }

    private function getTrayectoIdByLegacy(string $legacyId): ?int
    {
        $id = $this->newConn->fetchOne(
            "SELECT id FROM trayecto WHERE legacy_id = :lid",
            ["lid" => $legacyId],
        );
        return $id !== false ? (int) $id : null;
    }

    private function getFirstEmpresaId(): ?int
    {
        $id = $this->newConn->fetchOne(
            "SELECT id FROM empresa ORDER BY id ASC LIMIT 1",
        );
        return $id !== false ? (int) $id : null;
    }

    private function sanitizeUtf8(array $row): array
    {
        $clean = [];
        foreach ($row as $k => $v) {
            $clean[$k] = is_string($v)
                ? mb_convert_encoding($v, "UTF-8", "ISO-8859-1")
                : $v;
        }
        return $clean;
    }

    private function fetchOld(string $sql, array $params = []): \Generator
    {
        $stmt = $this->oldPdo->prepare($sql);
        $stmt->execute($params);
        while ($row = $stmt->fetch()) {
            yield $this->sanitizeUtf8($row);
        }
    }

    private function fetchOldNoGenerator(string $sql, array $params = []): array
    {
        $stmt = $this->oldPdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return array_map(fn(array $r) => $this->sanitizeUtf8($r), $rows);
    }
}
