<?php

declare(strict_types=1);

namespace App\Migration\Salida;

use App\Migration\Mapeador;
use Doctrine\DBAL\Connection;

/**
 * Registros maestros que una salida del legado arrastra (empresa, estaciones,
 * bus con su marca/clase/pilotos/croquis, clientes, usuarios, agencias,
 * catálogos de la venta), creados bajo demanda y en orden de dependencias
 * para que cada INSERT cumpla las FK del modelo nuevo.
 *
 * Todo es idempotente: lo que ya existe se reutiliza (los maestros conservan
 * el id del legado; el bus se reconoce por `codigo`). Una referencia del
 * legado que no existe queda en null (si la columna lo admite) en vez de
 * romper la salida entera.
 *
 * Las cachés solo guardan lo ya escrito: si la transacción de una salida se
 * revierte, `restaurar()` las vacía junto con los contadores.
 */
class DependenciasLegado
{
    /** Tablas con id explícito del legado: su identidad se reajusta al final. */
    private const TABLAS_ID_EXPLICITO = [
        "empresa", "enclave", "agencia", "cliente", "usuario", "piloto",
        "bus_marca", "bus_clase", "tipo_pago", "moneda", "tipo_documento", "pais",
    ];

    /** @var array<string, array<int|string, int|null>> tabla → id legado → id nuevo */
    private array $ids = [];

    /** @var array<int, array<int, int>> bus → número de asiento → id */
    private array $asientos = [];

    /** @var array<string, int> */
    private array $contadores = [];

    /** @var array<int, array<string, mixed>|null> clientes del legado leídos por adelantado */
    private array $clientesLegado = [];

    public function __construct(
        private readonly Connection $db,
        private readonly LectorLegado $legado,
        private readonly Mapeador $mapeador,
    ) {}

    // ─── Empresa y lugares ─────────────────────────────────────────

    public function empresa(mixed $legacyId): ?int
    {
        return $this->resolver("empresa", $legacyId, function (int $id): ?int {
            $old = $this->legado->fila("SELECT * FROM empresa WHERE id = :id", ["id" => $id]);
            if ($old === null) {
                return null;
            }

            return $this->insertar("empresa", $this->mapeador->empresa($old));
        });
    }

    /**
     * Enclave de una estación (mismo id). Las agencias (`tipoEstacion_id = 4`)
     * no son enclaves: van por `agencia()`.
     */
    public function estacion(mixed $legacyId): ?int
    {
        return $this->resolver("enclave", $legacyId, function (int $id): ?int {
            $old = $this->legado->fila(
                "SELECT e.*, d.nombre AS departamento_nombre FROM estacion e
                 LEFT JOIN departamento d ON d.id = e.departamento_id WHERE e.id = :id",
                ["id" => $id],
            );
            if ($old === null || (int) ($old["tipoEstacion_id"] ?? 0) === Mapeador::TIPO_ESTACION_AGENCIA) {
                return null;
            }

            return $this->insertar("enclave", ["tipo" => "estacion"] + $this->mapeador->estacion($old), "estacion");
        });
    }

    /**
     * Agencia (estación tipo 4 del legado), como en la migración de
     * estáticos: el saldo actual entra como ajuste y sus usuarios ya migrados
     * quedan vinculados.
     */
    public function agencia(mixed $legacyId): ?int
    {
        return $this->resolver("agencia", $legacyId, function (int $id): ?int {
            $old = $this->legado->fila(
                "SELECT e.*, m.sigla AS moneda_sigla FROM estacion e
                 LEFT JOIN moneda m ON m.id = e.agencia_moneda_id
                 WHERE e.id = :id AND e.tipoEstacion_id = " . Mapeador::TIPO_ESTACION_AGENCIA,
                ["id" => $id],
            );
            if ($old === null) {
                return null;
            }
            $data = $this->mapeador->agencia($old);
            if ($this->insertar("agencia", $data) === null) {
                return null;
            }
            if ($data["saldo"] !== 0) {
                $this->db->executeStatement(
                    "INSERT INTO agencia_movimiento (agencia_id, tipo, monto, saldo_resultante, observacion, fecha)
                     VALUES (:agencia, 'ajuste', :monto, :monto, 'Saldo migrado del sistema anterior', NOW())",
                    ["agencia" => $id, "monto" => $data["saldo"]],
                );
            }
            $this->db->executeStatement(
                "UPDATE usuario SET agencia_id = :agencia, estacion_id = NULL
                 WHERE agencia_id IS NULL AND id = ANY(CAST(:usuarios AS int[]))",
                [
                    "agencia" => $id,
                    "usuarios" => "{" . implode(",", array_map("intval", array_column(
                        $this->legado->filas("SELECT id FROM custom_user WHERE estacion_id = :id", ["id" => $id]),
                        "id",
                    ))) . "}",
                ],
            );

            return $id;
        });
    }

    // ─── Bus ───────────────────────────────────────────────────────

    public function marca(mixed $legacyId): ?int
    {
        return $this->resolver("bus_marca", $legacyId, function (int $id): ?int {
            $old = $this->legado->fila("SELECT * FROM bus_marca WHERE id = :id", ["id" => $id]);

            return $old === null ? null : $this->insertar("bus_marca", [
                "id" => $id,
                "nombre" => mb_substr(trim((string) ($old["nombre"] ?? "")), 0, 20) ?: "Marca {$id}",
            ]);
        });
    }

    public function busClase(mixed $legacyId): ?int
    {
        return $this->resolver("bus_clase", $legacyId, function (int $id): ?int {
            $old = $this->legado->fila("SELECT id, nombre, activo FROM bus_clase WHERE id = :id", ["id" => $id]);

            return $old === null ? null : $this->insertar("bus_clase", [
                "id" => $id,
                "nombre" => mb_substr(trim((string) $old["nombre"]), 0, 50) ?: "Clase {$id}",
                "activo" => filter_var($old["activo"] ?? true, FILTER_VALIDATE_BOOL) ? "true" : "false",
            ]);
        });
    }

    public function piloto(mixed $legacyId): ?int
    {
        return $this->resolver("piloto", $legacyId, function (int $id): ?int {
            $old = $this->legado->fila("SELECT * FROM piloto WHERE id = :id", ["id" => $id]);
            if ($old === null) {
                return null;
            }
            $data = $this->mapeador->piloto($old);
            $data["empresa_id"] = $this->empresa($old["empresa_id"] ?? null);

            return $this->insertar("piloto", $data);
        });
    }

    /**
     * Bus por su código del legado, con su empresa (la del bus o, si falta,
     * la de la salida), marca, clase, piloto, copiloto y croquis.
     */
    public function bus(?string $codigo, ?int $empresaSalida): ?int
    {
        $codigo = trim((string) $codigo);
        if ($codigo === "") {
            return null;
        }
        if (array_key_exists($codigo, $this->ids["bus"] ?? [])) {
            return $this->ids["bus"][$codigo];
        }

        $old = $this->legado->fila(
            "SELECT b.*, bt.descripcion AS tipo_desc, bt.clase_id AS tipo_clase_id
             FROM bus b LEFT JOIN bus_tipo bt ON bt.id = b.tipo_id WHERE b.codigo = :codigo",
            ["codigo" => $codigo],
        );
        $id = $this->db->fetchOne("SELECT id FROM bus WHERE codigo = :codigo", ["codigo" => mb_substr($codigo, 0, 15)]);
        $id = $id === false ? null : (int) $id;

        if ($id === null && $old !== null) {
            $empresa = $this->empresa($old["empresa_id"] ?? null) ?? $empresaSalida;
            if ($empresa !== null) {
                $data = $this->mapeador->bus($old, $empresa);
                $data["gama"] = isset($old["tipo_desc"]) ? mb_substr(trim((string) $old["tipo_desc"]), 0, 50) : null;
                $data["clase_id"] = $this->busClase($old["tipo_clase_id"] ?? null);
                $data["marca_id"] = $this->marca($old["marca_id"] ?? null);
                $data["piloto_id"] = $this->piloto($old["piloto_id"] ?? null);
                $data["copiloto_id"] = $this->piloto($old["piloto_aux_id"] ?? null);
                if ($data["numeroSeguro"] !== null && $this->db->fetchOne(
                    "SELECT 1 FROM bus WHERE numeroseguro = :n",
                    ["n" => $data["numeroSeguro"]],
                ) !== false) {
                    $data["numeroSeguro"] = null;
                }
                $id = $this->insertar("bus", $data, "bus", true);
            }
        }

        if ($id !== null && $old !== null && !empty($old["tipo_id"])) {
            $this->croquis($id, (int) $old["tipo_id"]);
        }

        return $this->ids["bus"][$codigo] = $id;
    }

    /** Asiento del bus con ese número (los asientos se copian del tipo de bus). */
    public function asiento(int $busId, mixed $numero): ?int
    {
        if (!isset($this->asientos[$busId])) {
            $this->asientos[$busId] = array_map("intval", array_column(
                $this->db->fetchAllAssociative("SELECT numero, id FROM asiento WHERE bus_id = :bus", ["bus" => $busId]),
                "id",
                "numero",
            ));
        }

        return is_numeric($numero) ? ($this->asientos[$busId][(int) $numero] ?? null) : null;
    }

    /**
     * Copia al bus los asientos y las señales (chofer, puertas) de su tipo de
     * bus del legado. Se reconocen por `(bus, número)` y por celda.
     */
    private function croquis(int $busId, int $tipoBusId): void
    {
        $numeros = array_flip(array_map("intval", $this->db->fetchFirstColumn(
            "SELECT numero FROM asiento WHERE bus_id = :bus",
            ["bus" => $busId],
        )));
        foreach ($this->legado->filas("SELECT * FROM bus_asiento WHERE tipoBus_id = :tipo", ["tipo" => $tipoBusId]) as $old) {
            $data = $this->mapeador->asiento($old, $busId);
            if (isset($numeros[$data["numero"]])) {
                continue;
            }
            $this->db->executeStatement(
                "INSERT INTO asiento (numero, clase, planta, fila, columna, bus_id) VALUES (:numero, :clase, :planta, :fila, :columna, :bus_id)",
                $data,
            );
            $numeros[$data["numero"]] = true;
            $this->contar("asiento");
        }

        $celdas = [];
        foreach ($this->db->fetchAllNumeric("SELECT planta, fila, columna FROM bus_senal WHERE bus_id = :bus", ["bus" => $busId]) as $c) {
            $celdas[implode(":", $c)] = true;
        }
        foreach ($this->legado->filas(
            "SELECT s.*, t.nombre AS tipo_nombre FROM bus_senal s JOIN bus_senal_tipo t ON t.id = s.tipo_id WHERE s.tipoBus_id = :tipo",
            ["tipo" => $tipoBusId],
        ) as $old) {
            $data = $this->mapeador->senal($old, $busId);
            if ($data === null || isset($celdas[$c = "{$data["planta"]}:{$data["fila"]}:{$data["columna"]}"])) {
                continue;
            }
            $this->db->executeStatement(
                "INSERT INTO bus_senal (tipo, planta, fila, columna, bus_id) VALUES (:tipo, :planta, :fila, :columna, :bus_id)",
                $data,
            );
            $celdas[$c] = true;
            $this->contar("senal");
        }
        unset($this->asientos[$busId]);
    }

    // ─── Personas ──────────────────────────────────────────────────

    /**
     * Cliente con su tipo de documento y nacionalidad. Si el legado no lo
     * tiene, se crea uno genérico con el mismo id (el boleto lo exige).
     */
    public function cliente(mixed $legacyId): ?int
    {
        return $this->resolver("cliente", $legacyId, function (int $id): ?int {
            $old = array_key_exists($id, $this->clientesLegado)
                ? $this->clientesLegado[$id]
                : $this->legado->fila("SELECT * FROM cliente WHERE id = :id", ["id" => $id]);
            unset($this->clientesLegado[$id]);
            if ($old === null) {
                return $this->insertar("cliente", ["id" => $id, "nombre" => "Cliente", "apellido" => "Migrado"], "cliente_generico");
            }
            $data = $this->mapeador->cliente($old);
            $data["tipo_documento_id"] = $this->tipoDocumento($data["tipo_documento_id"]);
            $data["nacionalidad_id"] = $this->nacion($data["nacionalidad_id"]);

            return $this->insertar("cliente", $data);
        });
    }

    /**
     * Lee de una vez los clientes de los boletos de una salida que todavía no
     * están migrados (una consulta al legado en vez de una por boleto).
     *
     * @param list<mixed> $legacyIds
     */
    public function precargarClientes(array $legacyIds): void
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn($v) => is_numeric($v) ? (int) $v : 0, $legacyIds),
            fn(int $id) => $id > 0 && !array_key_exists($id, $this->ids["cliente"] ?? []),
        )));
        if ($ids === []) {
            return;
        }
        foreach (array_map("intval", $this->db->fetchFirstColumn(
            "SELECT id FROM cliente WHERE id IN (:ids)",
            ["ids" => $ids],
            ["ids" => \Doctrine\DBAL\ArrayParameterType::INTEGER],
        )) as $existe) {
            $this->ids["cliente"][$existe] = $existe;
        }
        $faltan = array_values(array_filter($ids, fn(int $id) => !isset($this->ids["cliente"][$id])));
        foreach (array_chunk($faltan, 500) as $lote) {
            foreach ($lote as $id) {
                $this->clientesLegado[$id] = null;
            }
            foreach ($this->legado->filas("SELECT * FROM cliente WHERE id IN (" . implode(",", $lote) . ")") as $fila) {
                $this->clientesLegado[(int) $fila["id"]] = $fila;
            }
        }
    }

    /** NIT y nombre del cliente ya migrado (receptor de la factura). */
    public function datosCliente(?int $id): array
    {
        $fila = $id === null ? false : $this->db->fetchAssociative(
            "SELECT nit, TRIM(CONCAT(nombre, ' ', COALESCE(apellido, ''))) AS nombre FROM cliente WHERE id = :id",
            ["id" => $id],
        );

        return $fila === false ? ["nit" => null, "nombre" => null] : $fila;
    }

    /** Usuario (empleado) con su estación de trabajo o su agencia. */
    public function usuario(mixed $legacyId): ?int
    {
        return $this->resolver("usuario", $legacyId, function (int $id): ?int {
            $old = $this->legado->fila("SELECT * FROM custom_user WHERE id = :id", ["id" => $id]);
            if ($old === null) {
                return null;
            }
            $data = $this->mapeador->usuario($old);
            if (!empty($old["estacion_id"])) {
                $data["agencia_id"] = $this->agencia($old["estacion_id"]);
                $data["estacion_id"] = $data["agencia_id"] === null ? $this->estacion($old["estacion_id"]) : null;
            }

            return $this->insertar("usuario", $data);
        });
    }

    // ─── Catálogos de la venta ─────────────────────────────────────

    public function tipoPago(mixed $legacyId): ?int
    {
        return $this->catalogo("tipo_pago", "SELECT * FROM tipo_pago WHERE id = :id", $legacyId, $this->mapeador->tipoPago(...));
    }

    public function moneda(mixed $legacyId): ?int
    {
        return $this->catalogo("moneda", "SELECT * FROM moneda WHERE id = :id", $legacyId, $this->mapeador->moneda(...));
    }

    public function tipoDocumento(mixed $legacyId): ?int
    {
        return $this->catalogo("tipo_documento", "SELECT * FROM tipo_documento WHERE id = :id", $legacyId, $this->mapeador->tipoDocumento(...));
    }

    public function nacion(mixed $legacyId): ?int
    {
        return $this->catalogo("pais", "SELECT * FROM nacionalidad WHERE id = :id", $legacyId, $this->mapeador->nacionalidad(...));
    }

    private function catalogo(string $tabla, string $sql, mixed $legacyId, callable $mapear): ?int
    {
        return $this->resolver($tabla, $legacyId, function (int $id) use ($tabla, $sql, $mapear): ?int {
            $old = $this->legado->fila($sql, ["id" => $id]);

            return $old === null ? null : $this->insertar($tabla, $mapear($old));
        });
    }

    // ─── Transacción, contadores e identidades ─────────────────────

    /** @return array<string, int> */
    public function contadores(): array
    {
        return $this->contadores;
    }

    /** @return array<string, int> estado de los contadores para `restaurar()` */
    public function marcar(): array
    {
        return $this->contadores;
    }

    /** Tras revertir la transacción de una salida: olvida lo que se escribió en ella. */
    public function restaurar(array $marca): void
    {
        $this->contadores = $marca;
        $this->ids = [];
        $this->asientos = [];
        $this->clientesLegado = [];
    }

    /** Tras insertar ids explícitos, la identidad sigue desde el mayor. */
    public function reiniciarIdentidades(): void
    {
        foreach (self::TABLAS_ID_EXPLICITO as $tabla) {
            $this->db->executeStatement(
                "SELECT setval(pg_get_serial_sequence('{$tabla}', 'id'), GREATEST(COALESCE((SELECT MAX(id) FROM {$tabla}), 0) + 1, 1), false)",
            );
        }
    }

    // ─── Internos ──────────────────────────────────────────────────

    /**
     * Id nuevo de un maestro que conserva el id del legado: si ya está en la
     * tabla se reutiliza; si no, `$crear` lo inserta (o devuelve null).
     *
     * @param \Closure(int): ?int $crear
     */
    private function resolver(string $tabla, mixed $legacyId, \Closure $crear): ?int
    {
        if (!is_numeric($legacyId) || (int) $legacyId <= 0) {
            return null;
        }
        $id = (int) $legacyId;
        if (array_key_exists($id, $this->ids[$tabla] ?? [])) {
            return $this->ids[$tabla][$id];
        }
        $existe = $this->db->fetchOne("SELECT 1 FROM {$tabla} WHERE id = :id", ["id" => $id]) !== false;

        return $this->ids[$tabla][$id] = $existe ? $id : $crear($id);
    }

    /**
     * INSERT de una fila mapeada. Los maestros con id del legado usan
     * `ON CONFLICT DO NOTHING` (otro registro con el mismo nombre o código
     * único): en ese caso devuelve null y la referencia queda vacía.
     */
    private function insertar(string $tabla, array $data, ?string $contador = null, bool $generado = false): ?int
    {
        $campos = implode(", ", array_keys($data));
        $args = implode(", ", array_map(static fn($k) => ":{$k}", array_keys($data)));
        if ($generado) {
            $id = (int) $this->db->fetchOne("INSERT INTO {$tabla} ({$campos}) VALUES ({$args}) RETURNING id", $data);
        } else {
            $insertados = $this->db->executeStatement(
                "INSERT INTO {$tabla} ({$campos}) VALUES ({$args}) ON CONFLICT DO NOTHING",
                $data,
            );
            if ($insertados === 0) {
                $this->contar("{$tabla}_conflicto");

                return null;
            }
            $id = (int) $data["id"];
        }
        $this->contar($contador ?? $tabla);

        return $id;
    }

    public function contar(string $clave, int $n = 1): void
    {
        $this->contadores[$clave] = ($this->contadores[$clave] ?? 0) + $n;
    }
}
