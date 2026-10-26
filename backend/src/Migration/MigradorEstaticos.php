<?php

namespace App\Migration;

use App\Croquis\Moldes;
use App\Migration\Job\Progreso;
use App\Migration\Salida\RutasLegado;
use App\Ruta\Trayectos;
use App\Venta\Facturacion\CredencialesFel;
use DateTime;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;


/**
 * Migrates static (non-growing) entities from legacy FDN to the new system.
 *
 * Order matters: Empresa → Estacion → BusClase → Bus → Asiento/Señal → Cliente → Usuario
 * Rutas (with their paradas and the trayecto of each pair, ADR-030) and Tarifas
 * are handled separately due to their derivation logic.
 *
 * Static entities with numeric old PKs (empresa, estacion, cliente, usuario, tarifa)
 * reuse the old PK as the new id. Entities with string old PKs (bus, ruta)
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
        private CredencialesFel $credencialesFel,
        private Moldes $moldes,
        private RutasLegado $rutas,
        private Trayectos $trayectos,
        private CoordenadasEnclaves $coordenadas,
        private RolesUsuarioLegado $rolesUsuario,
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
            "bus_clase" => 0,
            "bus" => 0,
            "asiento" => 0,
            "senal" => 0,
            "cliente" => 0,
            "usuario" => 0,
            "ruta" => 0,
            "tarifa" => 0,
            "piloto" => 0,
            "bus_marca" => 0,
            "localidad" => 0,
            "tipo_pago" => 0,
            "moneda" => 0,
            "tipo_documento" => 0,
            "nacionalidad" => 0,
            "agencia" => 0,
            "fel" => 0,
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
                $contadores["tipo_pago"] = $this->migrarTiposPago($output);
                $contadores["moneda"] = $this->migrarMonedas($output);
                $contadores["tipo_documento"] = $this->migrarTiposDocumento($output);
                $contadores["nacionalidad"] = $this->migrarNacionalidades($output);
                $contadores["cliente"] = $this->migrarClientes($output);
                $contadores["usuario"] = $this->migrarUsuarios($output);
                $contadores["agencia"] = $this->migrarAgencias($output);
                $contadores["fel"] = $this->migrarFel($output);
                $contadores["bus_marca"] = $this->migrarMarcas($output);
                $contadores["bus_clase"] = $this->migrarBusClases($output);
                $contadores["bus"] = $this->migrarBuss($output);
                $contadores["asiento"] = $this->migrarAsientos($output);
                $contadores["senal"] = $this->migrarSenales($output);
                $contadores["ruta"] = $this->migrarRutas($output);
                $contadores["tarifa"] = $this->migrarTarifas($output);
            }
            foreach (self::TABLAS_ID_EXPLICITO as $tabla) {
                $this->reiniciarIdentidad($tabla);
            }
            // Cada bus con el molde de su distribución (ADR-027).
            if ($entities === [] || array_intersect($entities, ["bus", "asiento", "senal"]) !== []) {
                $contadores["croquis"] = $this->moldes->asociar();
            }
            $this->newConn->commit();
        } catch (\Throwable $e) {
            $this->newConn->rollBack();
            throw $e;
        }

        return $contadores;
    }

    /** Tablas que se insertan con id explícito del legacy. */
    private const TABLAS_ID_EXPLICITO = ["empresa", "enclave", "cliente", "usuario", "piloto", "bus_marca", "bus_clase", "localidad", "boleto_tarifa"];

    /**
     * Nombre canónico de entidad → método migrador.
     * Evita el patrón "migrar".ucfirst($x)."s" que rompe con bus_marca.
     */
    private const ENTIDAD_A_METODO = [
        "empresa" => "migrarEmpresas",
        "estacion" => "migrarEstacions",
        "cliente" => "migrarClientes",
        "usuario" => "migrarUsuarios",
        "bus_clase" => "migrarBusClases",
        "bus" => "migrarBuss",
        "asiento" => "migrarAsientos",
        "senal" => "migrarSenales",
        "ruta" => "migrarRutas",
        "tarifa" => "migrarTarifas",
        "piloto" => "migrarPilotos",
        "marca" => "migrarMarcas",
        "localidad" => "migrarLocalidads",
        "tipo_pago" => "migrarTiposPago",
        "moneda" => "migrarMonedas",
        "tipo_documento" => "migrarTiposDocumento",
        "nacionalidad" => "migrarNacionalidades",
        "agencia" => "migrarAgencias",
        "fel" => "migrarFel",
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
                "INSERT INTO empresa (id, nombre, alias, nombre_comercial, denominacion_social, nit, direccion, telefono, email) VALUES (:id, :nombre, :alias, :nombre_comercial, :denominacion_social, :nit, :direccion, :telefono, :email)",
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

    /**
     * Copia el departamento del legado a los enclaves ya migrados (mismo id
     * que `estacion.id`), sin migrar de nuevo. Devuelve los actualizados.
     */
    public function actualizarDepartamentos(): int
    {
        $count = 0;
        foreach ($this->fetchOld("SELECT e.id, d.nombre AS departamento_nombre FROM estacion e JOIN departamento d ON d.id = e.departamento_id") as $row) {
            $count += $this->newConn->executeStatement(
                "UPDATE enclave SET departamento = :departamento WHERE id = :id AND departamento IS DISTINCT FROM :departamento",
                ["id" => (int) $row["id"], "departamento" => mb_substr(trim((string) $row["departamento_nombre"]), 0, 60)],
            );
        }

        return $count;
    }

    private function migrarEstacions(?OutputInterface $output = null): int
    {
        if ($output) {
            $output->write("<info>Estaciones...</info>");
        }
        // Las agencias (tipo 4) no son enclaves: van a `agencia` (migrarAgencias).
        $rows = $this->fetchOld(
            "SELECT e.*, d.nombre AS departamento_nombre FROM estacion e LEFT JOIN departamento d ON d.id = e.departamento_id"
            . " WHERE e.activo = 1 AND (e.tipoEstacion_id IS NULL OR e.tipoEstacion_id <> " . self::TIPO_ESTACION_AGENCIA . ")",
        );
        $count = 0;

        foreach ($rows as $row) {
            $lid = (string) $row["id"];
            if ($this->existe("enclave", $lid)) {
                continue;
            }
            $data = $this->mapeador->estacion($row);
            $this->newConn->executeStatement(
                "INSERT INTO enclave (id, tipo, nombre, direccion, alias, latitud, longitud, departamento) VALUES (:id, 'estacion', :nombre, :direccion, :alias, :latitud, :longitud, :departamento)",
                $data,
            );
            $count++;
        }
        // El legado deja muchas estaciones sin GPS: se rellenan con las guardadas antes del reset.
        $this->coordenadas->completar();

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
        $tiposDocumento = array_flip(array_map("intval", $this->newConn->fetchFirstColumn("SELECT id FROM tipo_documento")));
        $naciones = array_flip(array_map("intval", $this->newConn->fetchFirstColumn("SELECT id FROM pais")));

        foreach ($rows as $row) {
            if ($progreso && $progreso->debeCancelar()) {
                break;
            }
            $lid = (string) $row["id"];
            if ($this->existe("cliente", $lid)) {
                continue;
            }
            $data = $this->mapeador->cliente($row);
            if (!isset($tiposDocumento[$data["tipo_documento_id"] ?? 0])) {
                $data["tipo_documento_id"] = null;
            }
            if (!isset($naciones[$data["nacionalidad_id"] ?? 0])) {
                $data["nacionalidad_id"] = null;
            }
            $this->newConn->executeStatement(
                "INSERT INTO cliente (id, nombre, apellido, nit, email, telefono, numero_documento, tipo_documento_id, nacionalidad_id) VALUES (:id, :nombre, :apellido, :nit, :email, :telefono, :numero_documento, :tipo_documento_id, :nacionalidad_id)",
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
        // Alcance de cada usuario en el legado (ADR-039), antes de recorrerlos: dblib no admite consultas anidadas.
        $alcance = ["empresas" => [], "estaciones" => []];
        foreach ($this->fetchOldNoGenerator("SELECT user_id, empresa_id FROM custom_user_empresa") as $r) {
            $alcance["empresas"][(int) $r["user_id"]][] = (int) $r["empresa_id"];
        }
        foreach ($this->fetchOldNoGenerator("SELECT user_id, estacion_id FROM custom_user_estacion") as $r) {
            $alcance["estaciones"][(int) $r["user_id"]][] = (int) $r["estacion_id"];
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
            // Estación de trabajo (valor inicial de la venta en taquilla).
            if (!empty($row["estacion_id"]) && $this->existe("enclave", (string) $row["estacion_id"])) {
                $this->newConn->executeStatement(
                    "UPDATE usuario SET estacion_id = :estacion WHERE id = :id",
                    ["estacion" => (int) $row["estacion_id"], "id" => (int) $row["id"]],
                );
            }
            // Accesos (ADR-039): sus roles con las empresas y estaciones que tenía en el legado.
            $this->rolesUsuario->asignar(
                (int) $row["id"],
                $row["roles"] ?? null,
                $alcance["empresas"][(int) $row["id"]] ?? [],
                [...(empty($row["estacion_id"]) ? [] : [(int) $row["estacion_id"]]), ...($alcance["estaciones"][(int) $row["id"]] ?? [])],
                RolesUsuarioLegado::activo($row),
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
            "SELECT b.*, bt.descripcion AS tipo_desc, bt.clase_id AS clase_id FROM bus b LEFT JOIN bus_tipo bt ON bt.id = b.tipo_id",
        );
        $count = 0;

        foreach ($rows as $row) {
            $claseId = $this->resolveBusClaseId((int) ($row["clase_id"] ?? 0));
            if ($this->existe("bus", $row["codigo"])) {
                // Buses migrados antes de que existiera la clase de bus.
                $this->newConn->executeStatement(
                    "UPDATE bus SET clase_id = :clase WHERE codigo = :codigo AND clase_id IS NULL",
                    ["clase" => $claseId, "codigo" => $row["codigo"]],
                );
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
            $data["clase_id"] = $claseId;

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

    // ─── Ruta y paradas (ADR-030) ───────────────────────────────────

    private function migrarRutas(?OutputInterface $output = null): int
    {
        if ($output) {
            $output->write("<info>Rutas...</info>");
        }
        $n = $this->rutas->migrarTodas();
        if ($output) {
            $output->writeln(" <info>{$n}</info>");
        }

        return $n;
    }

    // ─── Tarifa ────────────────────────────────────────────────────

    /**
     * Solo la más reciente de cada grupo de tarifas iguales (mismo origen,
     * destino, clase de asiento, clase de bus y horario), por
     * `fechaEfectividad` y luego id, como las elegía el legado. Las demás del
     * grupo son historial: no se migran, y si ya estaban migradas se borran.
     * El trayecto de su par origen→destino se crea si falta (ADR-030). No
     * se migran las de una estación o una clase de bus sin migrar. Las ganadoras se insertan o se corrigen (upsert por id).
     */
    private function migrarTarifas(?OutputInterface $output = null): int
    {
        if ($output) {
            $output->write("<info>Tarifas...</info>");
        }
        $rows = $this->fetchOld(
            "SELECT id, estacion_origen_id, estacion_destino_id, clase_asiento_id, clase_bus_id, usuario_creacion, tarifaValor,
                    CONVERT(varchar(19), fechaEfectividad, 120) AS vigente_desde,
                    CONVERT(varchar(5), horaInicialSalida, 108) AS hora_desde,
                    CONVERT(varchar(5), horaFinalSalida, 108) AS hora_hasta,
                    ROW_NUMBER() OVER (
                        PARTITION BY estacion_origen_id, estacion_destino_id, clase_asiento_id, clase_bus_id, horaInicialSalida, horaFinalSalida
                        ORDER BY fechaEfectividad DESC, id DESC
                    ) AS orden
               FROM tarifas_boleto",
        );

        $count = 0;
        $sobrantes = [];
        $sinTrayecto = 0;
        $sinClaseBus = 0;
        $defaultUsuarioId = $this->getFirstUsuarioId() ?? 1;

        foreach ($rows as $row) {
            if ((int) $row["orden"] > 1) {
                $sobrantes[] = (int) $row["id"];
                continue;
            }
            $trayectoId = $this->findTrayectoIdPorTarifa($row);
            if ($trayectoId === null) {
                $sobrantes[] = (int) $row["id"];
                $sinTrayecto++;
                continue;
            }
            $busClaseId = $this->resolveBusClaseId((int) ($row["clase_bus_id"] ?? 0));
            if ($busClaseId === null) {
                $sobrantes[] = (int) $row["id"];
                $sinClaseBus++;
                continue;
            }

            $clase = ((int) ($row["clase_asiento_id"] ?? 0)) === 2 ? "B" : "A";
            $usuarioId = $this->resolveUsuarioId((int) ($row["usuario_creacion"] ?? 0)) ?? $defaultUsuarioId;

            $data = $this->mapeador->boletoTarifa(
                $row,
                $clase,
                $usuarioId,
                $trayectoId,
                $busClaseId,
            );
            $this->newConn->executeStatement(
                'INSERT INTO boleto_tarifa (id, nombre, precio_monto, precio_moneda, clase, empresa_id, bus_id, bus_clase_id, trayecto_id, hora_desde, hora_hasta, vigente_desde, usuario_id)
                 VALUES (:id, :nombre, :precio_monto, :precio_moneda, :clase, :empresa_id, :bus_id, :bus_clase_id, :trayecto_id, :hora_desde, :hora_hasta, :vigente_desde, :usuario_id)
                 ON CONFLICT (id) DO UPDATE SET nombre = EXCLUDED.nombre, precio_monto = EXCLUDED.precio_monto, precio_moneda = EXCLUDED.precio_moneda,
                     clase = EXCLUDED.clase, empresa_id = EXCLUDED.empresa_id, bus_id = EXCLUDED.bus_id, bus_clase_id = EXCLUDED.bus_clase_id,
                     trayecto_id = EXCLUDED.trayecto_id, hora_desde = EXCLUDED.hora_desde, hora_hasta = EXCLUDED.hora_hasta,
                     vigente_desde = EXCLUDED.vigente_desde, usuario_id = EXCLUDED.usuario_id',
                $data,
            );
            $count++;
        }

        foreach (array_chunk($sobrantes, 1000) as $ids) {
            $this->newConn->executeStatement(
                "DELETE FROM boleto_tarifa WHERE id IN (:ids)",
                ["ids" => $ids],
                ["ids" => ArrayParameterType::INTEGER],
            );
        }

        if ($output) {
            $output->writeln(sprintf(
                " <info>%d</info> (omitidas: %d historial, %d sin trayecto, %d sin clase de bus)",
                $count,
                count($sobrantes) - $sinTrayecto - $sinClaseBus,
                $sinTrayecto,
                $sinClaseBus,
            ));
        }
        return $count;
    }

    // ─── BusClase ──────────────────────────────────────────────────

    private function migrarBusClases(?OutputInterface $output = null): int
    {
        if ($output) {
            $output->write("<info>Clases de bus...</info>");
        }
        $count = 0;

        foreach ($this->fetchOld("SELECT id, nombre, activo FROM bus_clase") as $row) {
            if ($this->existe("bus_clase", (string) $row["id"])) {
                continue;
            }
            $this->newConn->executeStatement(
                "INSERT INTO bus_clase (id, nombre, activo) VALUES (:id, :nombre, :activo)",
                [
                    "id" => (int) $row["id"],
                    "nombre" => mb_substr(trim((string) $row["nombre"]), 0, 50),
                    "activo" => (bool) $row["activo"] ? "true" : "false",
                ],
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

        // El par existe aunque ninguna ruta lo tenga: la tarifa es un hecho del legado.
        return $origen === $destino ? null : $this->trayectos->par($origen, $destino);
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

    // ─── Catálogos de la venta (ADR-021) ───────────────────────────

    /** `estacion.tipoEstacion_id` de las agencias en el legado. */
    private const TIPO_ESTACION_AGENCIA = 4;

    private function migrarTiposPago(?OutputInterface $output = null): int
    {
        return $this->migrarCatalogo($output, "Tipos de pago", "SELECT * FROM tipo_pago", "tipo_pago", $this->mapeador->tipoPago(...));
    }

    private function migrarMonedas(?OutputInterface $output = null): int
    {
        return $this->migrarCatalogo($output, "Monedas", "SELECT * FROM moneda", "moneda", $this->mapeador->moneda(...));
    }

    private function migrarTiposDocumento(?OutputInterface $output = null): int
    {
        return $this->migrarCatalogo($output, "Tipos de documento", "SELECT * FROM tipo_documento", "tipo_documento", $this->mapeador->tipoDocumento(...));
    }

    private function migrarNacionalidades(?OutputInterface $output = null): int
    {
        return $this->migrarCatalogo($output, "Nacionalidades", "SELECT * FROM nacionalidad", "pais", $this->mapeador->nacionalidad(...));
    }

    /**
     * Catálogo con PK numérica del legado (se conserva el id). Un nombre ya
     * existente con otro id se salta (`ON CONFLICT DO NOTHING`).
     */
    private function migrarCatalogo(?OutputInterface $output, string $etiqueta, string $sql, string $tabla, callable $mapear): int
    {
        if ($output) {
            $output->write("<info>{$etiqueta}...</info>");
        }
        $count = 0;
        foreach ($this->fetchOld($sql) as $row) {
            if ($this->existe($tabla, (string) $row["id"])) {
                continue;
            }
            $data = $mapear($row);
            $campos = implode(", ", array_keys($data));
            $args = implode(", ", array_map(static fn($k) => ":{$k}", array_keys($data)));
            $count += $this->newConn->executeStatement("INSERT INTO {$tabla} ({$campos}) VALUES ({$args}) ON CONFLICT DO NOTHING", $data);
        }
        $this->reiniciarIdentidad($tabla);
        if ($output) {
            $output->writeln(" <info>{$count}</info>");
        }

        return $count;
    }

    /**
     * Agencias: estaciones del legado con `tipoEstacion_id = 4`. El saldo
     * migrado entra como un ajuste en el libro de la agencia, y sus usuarios
     * (`custom_user.estacion_id`) quedan vinculados a ella.
     */
    private function migrarAgencias(?OutputInterface $output = null): int
    {
        if ($output) {
            $output->write("<info>Agencias...</info>");
        }
        $rows = $this->fetchOld(
            "SELECT e.*, m.sigla AS moneda_sigla FROM estacion e LEFT JOIN moneda m ON m.id = e.agencia_moneda_id WHERE e.tipoEstacion_id = " . self::TIPO_ESTACION_AGENCIA,
        );
        $count = 0;
        $ahora = (new DateTime())->format("Y-m-d H:i:s");
        foreach ($rows as $row) {
            if ($this->existe("agencia", (string) $row["id"])) {
                continue;
            }
            $data = $this->mapeador->agencia($row);
            $this->newConn->executeStatement(
                "INSERT INTO agencia (id, nombre, direccion, saldo, moneda, porcentaje_bonificacion, activo, legacy_id) VALUES (:id, :nombre, :direccion, :saldo, :moneda, :porcentaje_bonificacion, :activo, :legacy_id)",
                $data,
            );
            if ($data["saldo"] !== 0) {
                $this->newConn->executeStatement(
                    "INSERT INTO agencia_movimiento (agencia_id, tipo, monto, saldo_resultante, observacion, fecha) VALUES (:agencia, 'ajuste', :monto, :monto, 'Saldo migrado del sistema anterior', :fecha)",
                    ["agencia" => $data["id"], "monto" => $data["saldo"], "fecha" => $ahora],
                );
            }
            $usuarios = array_map("intval", array_column($this->fetchOldNoGenerator(
                "SELECT id FROM custom_user WHERE estacion_id = :estacion",
                ["estacion" => $row["id"]],
            ), "id"));
            if ($usuarios !== []) {
                $this->newConn->executeStatement(
                    "UPDATE usuario SET agencia_id = :agencia, estacion_id = NULL WHERE id IN (" . implode(",", $usuarios) . ")",
                    ["agencia" => $data["id"]],
                );
            }
            $count++;
        }
        $this->reiniciarIdentidad("agencia");
        $bonos = $this->migrarBonosAgencias();
        $depositos = $this->migrarDepositosAgencia();
        if ($output) {
            $output->writeln(" <info>{$count}</info> (depósitos: {$depositos}, bonos: {$bonos})");
        }

        return $count;
    }

    private const OBSERVACION_BONO_MIGRADO = "Bono migrado del sistema anterior";

    /**
     * El legado lleva el bono (`agencia_bonificacion`) como un saldo aparte;
     * el modelo nuevo tiene uno solo (ADR-021), así que entra como una
     * bonificación en el libro de la agencia. Una sola vez por agencia: el
     * valor es el del legado en la corrida en que se agrega.
     */
    private function migrarBonosAgencias(): int
    {
        $count = 0;
        $ahora = (new DateTime())->format("Y-m-d H:i:s");
        foreach ($this->fetchOld("SELECT id, agencia_bonificacion FROM estacion WHERE tipoEstacion_id = " . self::TIPO_ESTACION_AGENCIA . " AND agencia_bonificacion > 0") as $row) {
            $centavos = (int) round(((float) $row["agencia_bonificacion"]) * 100);
            $agencia = (int) $row["id"];
            $ya = $this->newConn->fetchOne(
                "SELECT 1 FROM agencia_movimiento WHERE agencia_id = :agencia AND tipo = 'bonificacion' AND observacion = :obs",
                ["agencia" => $agencia, "obs" => self::OBSERVACION_BONO_MIGRADO],
            );
            if ($centavos <= 0 || $ya) {
                continue;
            }
            $saldo = $this->newConn->fetchOne("UPDATE agencia SET saldo = saldo + :c WHERE id = :agencia RETURNING saldo", ["c" => $centavos, "agencia" => $agencia]);
            if ($saldo === false) {
                continue;
            }
            $this->newConn->executeStatement(
                "INSERT INTO agencia_movimiento (agencia_id, tipo, monto, saldo_resultante, observacion, fecha) VALUES (:agencia, 'bonificacion', :monto, :saldo, :obs, :fecha)",
                ["agencia" => $agencia, "monto" => $centavos, "saldo" => (int) $saldo, "obs" => self::OBSERVACION_BONO_MIGRADO, "fecha" => $ahora],
            );
            $count++;
        }

        return $count;
    }

    /**
     * Depósitos de agencia del legado con su estado (ADR-034). No mueven el
     * saldo (ya migrado): un depósito que sigue registrado se acredita en el
     * modelo nuevo y entonces suma. Los ya migrados se saltan.
     */
    private function migrarDepositosAgencia(): int
    {
        $agencias = array_flip(array_map("intval", $this->newConn->fetchFirstColumn("SELECT id FROM agencia")));
        $count = 0;
        foreach ($this->fetchOld("SELECT d.*, m.sigla AS moneda_sigla FROM agencia_deposito d LEFT JOIN moneda m ON m.id = d.moneda_id") as $row) {
            if (!isset($agencias[(int) $row["estacion_id"]])) {
                continue;
            }
            $count += $this->newConn->executeStatement(
                "INSERT INTO agencia_deposito (agencia_id, fecha, importe, moneda, numero_boleta, aplica_bono, bono, observacion, estado, motivo_rechazo, creado_por_id, creado_en, legacy_id)
                 VALUES (:agencia, :fecha, :importe, :moneda, :numero_boleta, :aplica_bono, :bono, :observacion, :estado, :motivo_rechazo, (SELECT id FROM usuario WHERE id = :usuario), :creado_en, :legacy_id)
                 ON CONFLICT (legacy_id) DO NOTHING",
                $this->mapeador->depositoAgencia($row),
            );
        }

        return $count;
    }

    /**
     * Datos FEL de cada empresa desde `factura_emisor`: afiliación al IVA,
     * credenciales del certificador (la clave se guarda cifrada) y el
     * establecimiento por defecto (código 1) si la empresa no tiene.
     */
    private function migrarFel(?OutputInterface $output = null): int
    {
        if ($output) {
            $output->write("<info>Facturación electrónica (emisores)...</info>");
        }
        $count = 0;
        foreach ($this->fetchOld("SELECT * FROM factura_emisor") as $row) {
            if (isset($row["activo"]) && $row["activo"] !== null && !filter_var($row["activo"], FILTER_VALIDATE_BOOL)) {
                continue;
            }
            $empresaId = $row["empresa_id"] ? $this->resolveEmpresaId((int) $row["empresa_id"]) : null;
            if (!$empresaId) {
                continue;
            }
            $data = $this->mapeador->emisorFel($row);
            if ($data["afiliacion_iva"] !== null) {
                $this->newConn->executeStatement(
                    "UPDATE empresa SET afiliacion_iva = :afiliacion WHERE id = :id",
                    ["afiliacion" => $data["afiliacion_iva"], "id" => $empresaId],
                );
            }
            if ($data["usuario"] !== null && $data["clave"] !== null) {
                $this->newConn->executeStatement(
                    "INSERT INTO credencial_fel (empresa_id, usuario, clave_cifrada) VALUES (:empresa, :usuario, :clave)
                     ON CONFLICT (empresa_id) DO UPDATE SET usuario = EXCLUDED.usuario, clave_cifrada = EXCLUDED.clave_cifrada",
                    ["empresa" => $empresaId, "usuario" => $data["usuario"], "clave" => $this->credencialesFel->cifrar($data["clave"])],
                );
            }
            $this->newConn->executeStatement(
                "INSERT INTO establecimiento (empresa_id, estacion_id, codigo, nombre)
                 SELECT :empresa, NULL, 1, 'Principal'
                 WHERE NOT EXISTS (SELECT 1 FROM establecimiento WHERE empresa_id = :empresa AND estacion_id IS NULL)",
                ["empresa" => $empresaId],
            );
            $count++;
        }
        $count += $this->migrarEstablecimientos();
        if ($output) {
            $output->writeln(" <info>{$count}</info>");
        }

        return $count;
    }

    /** Columna de `estacion` del legado con el número de establecimiento SAT de cada empresa (id del legado). */
    private const ESTABLECIMIENTO_POR_EMPRESA = [
        "numEstablecimientoSat" => 1,
        "numEstablecimientoSatMayaDeOro" => 2,
        "numEstablecimientoSatMitocha" => 4,
        "numEstablecimientoStarbus" => 6,
        "numEstablecimientoSatRosita" => 7,
    ];

    /** Estación del legado a la que se cargan las ventas de la página: su establecimiento es el de las ventas en línea. */
    private const ESTACION_WEB_LEGADO = 5;

    /**
     * Establecimiento SAT de cada empresa en cada estación, como los elige el
     * legado (`SatRestService::extraerDatos`). El de la estación web pasa a ser
     * el de por defecto de la empresa, que es el que usan las ventas en línea.
     */
    private function migrarEstablecimientos(): int
    {
        $count = 0;
        $columnas = implode(", ", array_keys(self::ESTABLECIMIENTO_POR_EMPRESA));
        foreach ($this->fetchOld("SELECT id, nombre, {$columnas} FROM estacion") as $row) {
            $estacionId = $this->resolveEstacionId((int) $row["id"]);
            if ($estacionId === null) {
                continue;
            }
            foreach (self::ESTABLECIMIENTO_POR_EMPRESA as $columna => $empresaLegado) {
                $codigo = (int) ($row[$columna] ?? 0);
                $empresaId = $this->resolveEmpresaId($empresaLegado);
                if ($codigo <= 0 || $empresaId === null) {
                    continue;
                }
                $this->newConn->executeStatement(
                    "INSERT INTO establecimiento (empresa_id, estacion_id, codigo, nombre) VALUES (:empresa, :estacion, :codigo, :nombre)
                     ON CONFLICT (empresa_id, estacion_id) DO UPDATE SET codigo = EXCLUDED.codigo",
                    ["empresa" => $empresaId, "estacion" => $estacionId, "codigo" => $codigo, "nombre" => $row["nombre"] ?? null],
                );
                if ((int) $row["id"] === self::ESTACION_WEB_LEGADO) {
                    $this->newConn->executeStatement(
                        "INSERT INTO establecimiento (empresa_id, estacion_id, codigo, nombre)
                         SELECT :empresa, NULL, :codigo, 'Principal'
                         WHERE NOT EXISTS (SELECT 1 FROM establecimiento WHERE empresa_id = :empresa AND estacion_id IS NULL)",
                        ["empresa" => $empresaId, "codigo" => $codigo],
                    );
                    $this->newConn->executeStatement(
                        "UPDATE establecimiento SET codigo = :codigo WHERE empresa_id = :empresa AND estacion_id IS NULL",
                        ["empresa" => $empresaId, "codigo" => $codigo],
                    );
                }
                $count++;
            }
        }

        return $count;
    }

    /** Tras insertar ids explícitos, la identidad sigue desde el mayor. */
    private function reiniciarIdentidad(string $tabla): void
    {
        $this->newConn->executeStatement(
            "SELECT setval(pg_get_serial_sequence('{$tabla}', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM {$tabla}), 0) + 1, 1), false)",
        );
    }

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
            "bus_marca",
            "bus_clase",
            "tipo_pago",
            "moneda",
            "tipo_documento",
            "pais",
            "agencia"
                => "SELECT 1 FROM {$tabla} WHERE id = :lid",
            "bus" => "SELECT 1 FROM {$tabla} WHERE codigo = :lid",
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

    private function resolveBusClaseId(int $oldId): ?int
    {
        return $oldId > 0 && $this->existe("bus_clase", (string) $oldId) ? $oldId : null;
    }

    private function resolveUsuarioId(int $oldId): ?int
    {
        return $oldId > 0 && $this->existe("usuario", (string) $oldId) ? $oldId : null;
    }

    private function getBusIdByLegacy(string $codigo): ?int
    {
        $id = $this->newConn->fetchOne(
            "SELECT id FROM bus WHERE codigo = :lid",
            ["lid" => $codigo],
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
