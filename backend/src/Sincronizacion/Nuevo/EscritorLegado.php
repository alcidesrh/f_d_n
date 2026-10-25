<?php

declare(strict_types=1);

namespace App\Sincronizacion\Nuevo;

use App\Entity\Enum\EstadoEncomienda;
use App\Entity\Enum\ModalidadEncomienda;
use App\Sincronizacion\Identidades;
use App\Sincronizacion\Legado\ConexionLegado;
use App\Venta\Excepcion\AsientosNoDisponibles;
use App\Venta\Facturacion\CertificadorSimulado;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Escribe en el legado lo que se hace aquí (ADR-042): boletos (verificando
 * que el asiento siga libre allá), sus cambios de estado, clientes,
 * encomiendas y los datos de la factura electrónica.
 *
 * Todo lo que escribe lleva `identificador_web` con el prefijo `FDN:`: así la
 * importación no lo trae de vuelta y la conciliación encuentra lo que quedó
 * huérfano. Lo que el legado exige y aquí no existe (un usuario del legado,
 * una serie de factura) sale de lo más parecido o de
 * `SINCRONIZACION_USUARIO_LEGADO`.
 */
class EscritorLegado
{
    /** @var array<int, ?int> usuario de aquí → del legado (o null si no existe allá) */
    private array $usuarios = [];

    public function __construct(
        private readonly ConexionLegado $legado,
        private readonly Connection $db,
        #[Autowire(env: "default::int:SINCRONIZACION_USUARIO_LEGADO")] private readonly ?int $usuarioRespaldo = null,
    ) {}

    /** Tras un error: la próxima escritura abre otra conexión (la de ahora pudo morir). */
    public function cerrarConexion(): void
    {
        $this->legado->cerrar();
    }

    // ─── Boletos ───────────────────────────────────────────────────

    /**
     * Escribe los boletos en el legado en una transacción, después de
     * comprobar con bloqueo que sus asientos están libres allá (boletos que
     * ocupan y reservaciones vigentes).
     *
     * @param list<BoletoParaLegado> $boletos
     * @param list<string>           $liberados boletos del legado que la misma operación libera
     *                                          (una reasignación entre asientos de la selección)
     *
     * @return array<string, int> clave → id del boleto en el legado
     *
     * @throws AsientosNoDisponibles si el legado ya los vendió
     */
    public function ocupar(array $boletos, array $liberados = []): array
    {
        if ($boletos === []) {
            return [];
        }

        return $this->legado->transaccion(function () use ($boletos, $liberados): array {
            $ids = [];
            $porSalida = [];
            foreach ($boletos as $b) {
                $porSalida[$b->salidaLegado][] = $b;
            }
            foreach ($porSalida as $salida => $deSalida) {
                $asientos = $this->asientosLibres($salida, array_map(static fn(BoletoParaLegado $b) => $b->asiento, $deSalida), $liberados);
                foreach ($deSalida as $b) {
                    try {
                        $ids[$b->clave] = $this->insertarBoleto($b, $asientos[$b->asiento]);
                    } catch (\PDOException $e) {
                        // El índice único del legado (salida, asiento, estado): otro lo ocupó.
                        if (str_contains($e->getMessage(), "CUSTOM_IDX_BOLETO_SALIDA_ASIENTO_ESTADO")) {
                            throw AsientosNoDisponibles::numeros([$b->asiento]);
                        }

                        throw $e;
                    }
                }
            }

            return $ids;
        });
    }

    /**
     * Cambia el estado de un boleto del legado y lo anota en su bitácora.
     * Si el nuevo estado vuelve a ocupar el asiento (revertir una
     * cancelación), primero comprueba que siga libre allá.
     *
     * @throws AsientosNoDisponibles
     */
    public function cambiarEstado(string $legacyId, int $estado, ?int $usuario, string $descripcion): void
    {
        $this->legado->transaccion(function () use ($legacyId, $estado, $usuario, $descripcion) {
            $actual = $this->legado->fila(
                "SELECT b.id, b.estado_id, b.salida_id, ba.numero FROM boleto b WITH (UPDLOCK)
                   LEFT JOIN bus_asiento ba ON ba.id = b.asiento_bus_id WHERE b.id = :id",
                ["id" => $legacyId],
            ) ?? throw new \RuntimeException("El boleto {$legacyId} no está en el legado.");
            if ((int) $actual["estado_id"] === $estado) {
                return;
            }
            if (Traduccion::ocupa($estado) && !Traduccion::ocupa((int) $actual["estado_id"])) {
                $this->asientosLibres((int) $actual["salida_id"], [(int) $actual["numero"]], [$legacyId]);
            }
            $usuarioLegado = $this->usuario($usuario);
            $this->legado->ejecutar(
                "UPDATE boleto SET estado_id = :estado, fecha_actualizacion = GETDATE(), usuario_actualizacion_id = :usuario WHERE id = :id",
                ["estado" => $estado, "usuario" => $usuarioLegado, "id" => $legacyId],
            );
            $this->bitacoraBoleto((int) $legacyId, $estado, $usuarioLegado, $descripcion);
        });
    }

    /**
     * Asientos de la salida del legado por número (`bus_asiento` del tipo de
     * bus de la salida), si están libres. Bloquea lo leído hasta el commit:
     * otra venta de FDN espera y la del legado también.
     *
     * @param list<int>    $numeros
     * @param list<string> $excepto boletos del legado que no cuentan (se están liberando)
     *
     * @return array<int, int> número → `bus_asiento.id`
     *
     * @throws AsientosNoDisponibles
     */
    private function asientosLibres(int $salida, array $numeros, array $excepto = []): array
    {
        $tipoBus = $this->legado->valor("SELECT tipo_bus_id FROM salida WITH (UPDLOCK) WHERE id = :id", ["id" => $salida])
            ?? throw new \RuntimeException("La salida {$salida} no está en el legado.");
        $enLista = implode(",", array_map("intval", $numeros));
        $asientos = [];
        foreach ($this->legado->filas(
            "SELECT id, numero FROM bus_asiento WHERE tipoBus_id = :tipo AND numero IN ({$enLista})",
            ["tipo" => $tipoBus],
        ) as $a) {
            $asientos[(int) $a["numero"]] = (int) $a["id"];
        }
        $faltan = array_diff($numeros, array_keys($asientos));
        if ($faltan !== []) {
            throw new \RuntimeException(sprintf("La salida %d del legado no tiene los asientos %s.", $salida, implode(", ", $faltan)));
        }

        $ocupan = implode(",", Traduccion::OCUPAN_LEGADO);
        $fuera = implode(",", array_map("intval", [0, ...$excepto]));
        $ocupados = array_map("intval", array_column($this->legado->filas(
            "SELECT ba.numero FROM boleto b WITH (UPDLOCK, HOLDLOCK)
               JOIN bus_asiento ba ON ba.id = b.asiento_bus_id
              WHERE b.salida_id = :salida AND b.estado_id IN ({$ocupan}) AND ba.numero IN ({$enLista}) AND b.id NOT IN ({$fuera})
             UNION
             SELECT ba.numero FROM reservacion r WITH (UPDLOCK, HOLDLOCK)
               JOIN bus_asiento ba ON ba.id = r.asiento_bus_id
              WHERE r.salida_id = :salida2 AND r.estado_id = :vigente AND ba.numero IN ({$enLista})",
            ["salida" => $salida, "salida2" => $salida, "vigente" => Traduccion::RESERVACION_VIGENTE],
        ), "numero"));
        if ($ocupados !== []) {
            throw AsientosNoDisponibles::numeros(array_values(array_unique($ocupados)));
        }

        return $asientos;
    }

    private function insertarBoleto(BoletoParaLegado $b, int $asientoBus): int
    {
        $usuario = $this->usuario($b->usuario);
        $precio = Traduccion::quetzales($b->precio);
        $moneda = $b->moneda ?? Traduccion::GTQ;
        // El legado exige tarifa y tipo de cambio a todo boleto que no sea cortesía
        // (sin ellos no deja anularlo ni editarlo); la cortesía no puede tenerlos.
        $cortesia = $b->tipoDocumento === Traduccion::DOCUMENTO_CORTESIA;
        $id = $this->legado->insertar("boleto", [
            "identificador_web" => Traduccion::PREFIJO . $b->clave,
            "asiento_bus_id" => $asientoBus,
            "revendidoEnEstacion" => false,
            "revendidoEnCamino" => false,
            "reasignado_id" => $b->reasignadoDe,
            "cliente_documento" => $this->cliente($b->comprador),
            "cliente_boleto" => $this->cliente($b->pasajero),
            "salida_id" => $b->salidaLegado,
            "tipo_pago_id" => $b->tipoPago,
            "estacion_origen_id" => $b->origen,
            "estacion_destino_id" => $b->destino,
            "tipo_documento_id" => $b->tipoDocumento,
            "precioCalculadoMonedaBase" => $precio,
            "precioCalculado" => $precio,
            "moneda_id" => $moneda,
            "tarifa_id" => $cortesia ? null : $this->tarifa($b->salidaLegado, $asientoBus, $b->origen, $b->destino),
            "tipo_cambio_id" => $cortesia ? null : $this->tipoCambio($moneda),
            "estado_id" => $b->estado,
            "observacion" => mb_substr(trim("Vendido en FDN. " . ($b->observacion ?? "")), 0, 255),
            "estacion_creacion_id" => $b->estacion,
            "fecha_creacion" => new \DateTimeImmutable(),
            "usuario_creacion_id" => $usuario,
        ]);
        $this->bitacoraBoleto($id, $b->estado, $usuario, "Emitido en FDN.");

        return $id;
    }

    /**
     * La tarifa del legado para el tramo, como la busca él al vender
     * (`TarifaBoletoRepository::getTarifaBoleto`): origen, destino, clase del
     * asiento, clase del bus del itinerario (o, sin tarifa para ella, la del
     * bus de la salida), hora de la salida y la vigente hoy.
     */
    private function tarifa(int $salida, int $asientoBus, int $origen, int $destino): ?int
    {
        $id = $this->legado->valor(
            "SELECT TOP 1 t.id
               FROM salida s
               JOIN bus_tipo ts ON ts.id = s.tipo_bus_id
               LEFT JOIN itineario i ON i.id = s.itinerario_id
               LEFT JOIN bus_tipo ti ON ti.id = i.tipo_bus_id
               JOIN bus_asiento a ON a.id = :asiento
               JOIN tarifas_boleto t ON t.estacion_origen_id = :origen AND t.estacion_destino_id = :destino
                AND t.clase_asiento_id = a.clase_id
                AND t.clase_bus_id IN (COALESCE(ti.clase_id, ts.clase_id), ts.clase_id)
                AND (t.horaInicialSalida IS NULL OR t.horaInicialSalida <= CAST(s.fecha AS time))
                AND (t.horaFinalSalida IS NULL OR t.horaFinalSalida >= CAST(s.fecha AS time))
                AND t.fechaEfectividad <= GETDATE()
              WHERE s.id = :salida
              ORDER BY CASE WHEN t.clase_bus_id = COALESCE(ti.clase_id, ts.clase_id) THEN 0 ELSE 1 END, t.fechaEfectividad DESC, t.id DESC",
            ["asiento" => $asientoBus, "origen" => $origen, "destino" => $destino, "salida" => $salida],
        );

        return $id === null ? null : (int) $id;
    }

    private function tipoCambio(int $moneda): ?int
    {
        $id = $this->legado->valor("SELECT TOP 1 id FROM tipo_cambio WHERE moneda_id = :m ORDER BY fecha DESC, id DESC", ["m" => $moneda]);

        return $id === null ? null : (int) $id;
    }

    /** El pasajero del boleto, si cambió aquí. */
    public function pasajero(string $legacyId, int $cliente): void
    {
        $legado = $this->cliente($cliente);
        $this->legado->ejecutar(
            "UPDATE boleto SET cliente_boleto = :c, fecha_actualizacion = GETDATE() WHERE id = :id AND cliente_boleto <> :c2",
            ["c" => $legado, "c2" => $legado, "id" => $legacyId],
        );
    }

    private function bitacoraBoleto(int $boleto, int $estado, ?int $usuario, string $descripcion): void
    {
        $this->legado->insertar("boleto_bitacora", [
            "boleto_id" => $boleto,
            "fecha" => new \DateTimeImmutable(),
            "estado_id" => $estado,
            "usuario_id" => $usuario,
            "descripcion" => mb_substr($descripcion, 0, 255),
        ]);
    }

    // ─── Factura de una venta ──────────────────────────────────────

    /**
     * La factura electrónica de una venta, en el legado: una
     * `factura_generada` por boleto (allá es una por boleto) con el UUID,
     * la serie y el número del DTE. Con la serie de la estación y la empresa;
     * sin ella, se anota en la observación del boleto. Si la factura se anuló
     * aquí, también allá. Las del certificador simulado no se llevan.
     *
     * @return int boletos tocados
     */
    public function facturaDeVenta(int $ventaId): int
    {
        $venta = $this->db->fetchAssociative(
            "SELECT v.id, v.estacion_id, v.agencia_id, v.usuario_id, v.moneda_id, v.referencia_pago,
                    f.uuid, f.serie, f.dte, f.fecha_certificacion, f.total_monto, f.anulada_en, f.certificador_nit, s.empresa_id
               FROM boleto_venta v
               JOIN factura f ON f.id = v.factura_id
               LEFT JOIN LATERAL (SELECT salida_id FROM boleto_asiento WHERE boleto_venta_id = v.id LIMIT 1) b ON true
               LEFT JOIN salida s ON s.id = b.salida_id
              WHERE v.id = :id",
            ["id" => $ventaId],
        );
        // Un DTE del certificador simulado (desarrollo) no es una factura: al legado no va.
        if ($venta === false || $venta["certificador_nit"] === CertificadorSimulado::NIT) {
            return 0;
        }
        $boletos = $this->db->fetchFirstColumn(
            "SELECT legacy_id FROM boleto_asiento WHERE boleto_venta_id = :id AND legacy_id IS NOT NULL",
            ["id" => $ventaId],
        );

        return $this->facturar($boletos, "boleto", $venta, Traduccion::SERVICIO_BOLETO);
    }

    /**
     * @param list<string>          $legados ids en el legado
     * @param array<string, mixed>  $f       datos de la venta y su factura
     */
    private function facturar(array $legados, string $tabla, array $f, int $servicio): int
    {
        $n = 0;
        foreach ($legados as $legado) {
            $this->legado->transaccion(function () use ($legado, $tabla, $f, $servicio, &$n) {
                $actual = $this->legado->fila("SELECT factura_generada_id FROM {$tabla} WITH (UPDLOCK) WHERE id = :id", ["id" => $legado]);
                if ($actual === null) {
                    return;
                }
                $fg = $actual["factura_generada_id"];
                if ($fg === null) {
                    $fg = $this->facturaGenerada($f, $servicio);
                    if ($fg === null) {
                        $this->legado->ejecutar(
                            "UPDATE {$tabla} SET observacion = LEFT(CONCAT(observacion, :nota), 4000) WHERE id = :id",
                            ["nota" => sprintf(" Factura FEL %s serie %s número %s.", $f["uuid"], $f["serie"], $f["dte"]), "id" => $legado],
                        );
                        $n++;

                        return;
                    }
                    $this->legado->ejecutar("UPDATE {$tabla} SET factura_generada_id = :fg WHERE id = :id", ["fg" => $fg, "id" => $legado]);
                    $n++;
                }
                if ($f["anulada_en"] !== null) {
                    $this->legado->ejecutar(
                        "UPDATE factura_generada SET fecha_anulacion = COALESCE(fecha_anulacion, :en), usuario_anulacion_id = COALESCE(usuario_anulacion_id, :usuario) WHERE id = :id",
                        ["en" => new \DateTimeImmutable((string) $f["anulada_en"]), "usuario" => $this->usuario($f["usuario_id"] === null ? null : (int) $f["usuario_id"]), "id" => $fg],
                    );
                }
            });
        }

        return $n;
    }

    /** @param array<string, mixed> $f */
    private function facturaGenerada(array $f, int $servicio): ?int
    {
        $estacion = $f["estacion_id"] ?? $f["agencia_id"] ?? null;
        $serie = $this->legado->valor(
            "SELECT TOP 1 id FROM factura WHERE empresa_id = :empresa AND servicio_estacion_id = :servicio AND activo = 1
              ORDER BY CASE WHEN estacion_id = :estacion THEN 0 ELSE 1 END, id DESC",
            ["empresa" => $f["empresa_id"] ?? 0, "servicio" => $servicio, "estacion" => $estacion ?? 0],
        );
        $tipoCambio = $this->legado->valor("SELECT TOP 1 id FROM tipo_cambio WHERE moneda_id = :m ORDER BY fecha DESC, id DESC", ["m" => Traduccion::GTQ]);
        $usuario = $this->usuario($f["usuario_id"] === null ? null : (int) $f["usuario_id"]);
        $estacionFactura = $estacion ?? $this->legado->valor("SELECT estacion_id FROM factura WHERE id = :id", ["id" => $serie]);
        if ($serie === null || $tipoCambio === null || $usuario === null || $estacionFactura === null) {
            return null;
        }

        return $this->legado->insertar("factura_generada", [
            "factura_id" => $serie,
            "servicio_estacion_id" => $servicio,
            "moneda_id" => $f["moneda_id"] ?? Traduccion::GTQ,
            "tipo_cambio_id" => $tipoCambio,
            "importeTotal" => Traduccion::quetzales($f["total_monto"]),
            "fecha" => new \DateTimeImmutable((string) ($f["fecha_certificacion"] ?? "now")),
            "autorizacionTarjeta" => $f["referencia_pago"] === null ? null : mb_substr((string) $f["referencia_pago"], 0, 20),
            "usuario_id" => $usuario,
            "estacion_id" => $estacionFactura,
            "observacion" => "Certificada en FDN.",
            "fecha_creacion" => new \DateTimeImmutable(),
            "sAutorizacionUUIDsat" => strtoupper((string) $f["uuid"]),
            "sNumeroDTEsat" => $f["dte"],
            "sSerieDTEsat" => $f["serie"],
            "sFechaCertificaDTEsat" => $f["fecha_certificacion"] === null ? null : new \DateTimeImmutable((string) $f["fecha_certificacion"]),
        ]);
    }

    // ─── Clientes y usuarios ───────────────────────────────────────

    /**
     * Id en el legado de un cliente de aquí. Los migrados tienen el mismo;
     * los creados aquí se buscan en `sync_vinculo` o por NIT, nombre y
     * documento (único en el legado) y, si no están, se crean.
     */
    public function cliente(int $id): int
    {
        if (!Identidades::esNuevo($id) && $this->legado->valor("SELECT 1 FROM cliente WHERE id = :id", ["id" => $id]) !== null) {
            return $id;
        }
        $vinculo = $this->db->fetchOne("SELECT id_legado FROM sync_vinculo WHERE entidad = 'cliente' AND id_nuevo = :id", ["id" => $id]);
        if ($vinculo !== false && $this->legado->valor("SELECT 1 FROM cliente WHERE id = :id", ["id" => $vinculo]) !== null) {
            return (int) $vinculo;
        }
        $datos = $this->datosCliente($id);
        $legado = $this->legado->valor(
            "SELECT TOP 1 id FROM cliente WHERE nit = :nit AND nombre = :nombre AND ((dpi IS NULL AND :sin = 1) OR dpi = :dpi)",
            ["nit" => $datos["nit"], "nombre" => $datos["nombre"], "sin" => $datos["dpi"] === null ? 1 : 0, "dpi" => $datos["dpi"] ?? ""],
        );
        $legado = $legado !== null ? (int) $legado : $this->legado->insertar("cliente", $datos + [
            "detallado" => true,
            "fecha_creacion" => new \DateTimeImmutable(),
            "nitCreacionCopia" => $datos["nit"],
            "nombreCreacionCopia" => $datos["nombre"],
        ]);
        $this->db->executeStatement(
            "INSERT INTO sync_vinculo (entidad, id_nuevo, id_legado) VALUES ('cliente', :nuevo, :legado)
             ON CONFLICT (entidad, id_nuevo) DO UPDATE SET id_legado = EXCLUDED.id_legado",
            ["nuevo" => $id, "legado" => $legado],
        );

        return $legado;
    }

    /** Lleva al legado los datos de un cliente que se editó aquí. */
    public function actualizarCliente(int $id): void
    {
        $legado = $this->cliente($id);
        $datos = $this->datosCliente($id);
        $sets = implode(", ", array_map(static fn(string $c) => "[{$c}] = :{$c}", array_keys($datos)));
        $this->legado->ejecutar("UPDATE cliente SET {$sets} WHERE id = :id", $datos + ["id" => $legado]);
    }

    /** @return array<string, mixed> columnas de `cliente` del legado */
    private function datosCliente(int $id): array
    {
        $c = $this->db->fetchAssociative(
            "SELECT nombre, apellido, nit, email, telefono, direccion, numero_documento, tipo_documento_id, nacionalidad_id FROM cliente WHERE id = :id",
            ["id" => $id],
        ) ?: throw new \RuntimeException("El cliente {$id} no existe.");
        $dpi = trim((string) $c["numero_documento"]);

        return Traduccion::nombreCliente($c["nombre"], $c["apellido"]) + [
            "nit" => Traduccion::nit($c["nit"]),
            "dpi" => $dpi === "" ? null : mb_substr($dpi, 0, 40),
            "correo" => $c["email"] === null ? null : mb_substr((string) $c["email"], 0, 40),
            "telefono" => $c["telefono"] === null ? null : mb_substr((string) $c["telefono"], 0, 21),
            "direccion" => $c["direccion"] === null ? null : mb_substr((string) $c["direccion"], 0, 150),
            "tipo_documento_id" => $c["tipo_documento_id"],
            "nacionalidad_id" => $c["nacionalidad_id"],
        ];
    }

    /**
     * Usuario del legado: el mismo id si existe allá (los migrados); si no,
     * el de respaldo configurado (puede ser null: el legado lo admite en boletos).
     */
    public function usuario(?int $id): ?int
    {
        if ($id === null) {
            return $this->usuarioRespaldo;
        }
        if (!array_key_exists($id, $this->usuarios)) {
            $this->usuarios[$id] = $this->legado->valor("SELECT 1 FROM custom_user WHERE id = :id", ["id" => $id]) !== null ? $id : null;
        }

        return $this->usuarios[$id] ?? $this->usuarioRespaldo;
    }

    // ─── Encomiendas ───────────────────────────────────────────────

    /**
     * Crea o pone al día la encomienda en el legado: una sola fila con todos
     * sus bultos (allá es un artículo por encomienda), su ruta y un evento
     * en su bitácora por cada cambio de estado. Devuelve su id allá.
     */
    public function encomienda(int $id): int
    {
        $e = $this->db->fetchAssociative(
            "SELECT e.*, r.legacy_id AS ruta_legado, s.legacy_id AS salida_legado, b.legacy_id AS boleto_legado
               FROM encomienda e
               LEFT JOIN ruta r ON r.id = e.ruta_id
               LEFT JOIN salida s ON s.id = e.salida_id
               LEFT JOIN boleto_asiento b ON b.id = e.boleto_id
              WHERE e.id = :id",
            ["id" => $id],
        ) ?: throw new \RuntimeException("La encomienda {$id} no existe.");

        return $this->legado->transaccion(function () use ($e): int {
            $legado = $e["legacy_id"] !== null ? (int) $e["legacy_id"] : null;
            if ($legado === null) {
                $legado = $this->insertarEncomienda($e);
                $this->db->executeStatement("UPDATE encomienda SET legacy_id = :l WHERE id = :id", ["l" => (string) $legado, "id" => $e["id"]]);
            }
            $this->estadoEncomienda($legado, $e);

            return $legado;
        });
    }

    /** @param array<string, mixed> $e fila de `encomienda` con la ruta, la salida y el boleto del legado */
    private function insertarEncomienda(array $e): int
    {
        if ($e["ruta_legado"] === null) {
            throw new \RuntimeException("La ruta de la encomienda {$e["id"]} no existe en el legado.");
        }
        $articulos = $this->db->fetchAllAssociative(
            "SELECT a.*, es.legacy_id AS especial_legado FROM encomienda_articulo a
               LEFT JOIN encomienda_especial es ON es.id = a.especial_id
              WHERE a.encomienda_id = :id ORDER BY a.id",
            ["id" => $e["id"]],
        );
        $primero = $articulos[0] ?? [];
        $unEspecial = count($articulos) === 1 && ($primero["especial_legado"] ?? null) !== null;
        $modalidad = ModalidadEncomienda::from((string) $e["modalidad"]);
        $usuario = $this->usuario($e["usuario_id"] === null ? null : (int) $e["usuario_id"])
            ?? throw new \RuntimeException("El legado exige un usuario: configure SINCRONIZACION_USUARIO_LEGADO.");
        $total = Traduccion::quetzales($modalidad === ModalidadEncomienda::INTERNA ? 0 : $e["total_monto"]);
        $descripcion = implode("; ", array_filter(array_map(
            static fn(array $a) => trim(sprintf("%d × %s", $a["cantidad"], $a["descripcion"] ?? $a["tipo"])),
            $articulos,
        )));

        $legado = $this->legado->insertar("encomienda", [
            "identificador_web" => Traduccion::PREFIJO . "encomienda:" . $e["id"],
            "cantidad" => max(1, array_sum(array_column($articulos, "cantidad"))),
            "tipo_encomienda_id" => Traduccion::tipoEncomienda($unEspecial ? (string) $primero["especial_legado"] : null),
            "tipo_encomienda_especial_id" => $unEspecial ? $primero["especial_legado"] : null,
            "ruta_codigo" => $e["ruta_legado"],
            "peso" => !$unEspecial && isset($primero["peso"]) ? (int) round((float) $primero["peso"]) : null,
            "alto" => !$unEspecial ? ($primero["alto"] ?? null) : null,
            "ancho" => !$unEspecial ? ($primero["ancho"] ?? null) : null,
            "profundidad" => !$unEspecial ? ($primero["largo"] ?? null) : null,
            "descripcion" => $descripcion === "" ? null : $descripcion,
            "cliente_remitente" => $this->cliente((int) $e["remitente_id"]),
            "cliente_destinatario" => $this->cliente((int) ($e["destinatario_id"] ?? $e["remitente_id"])),
            "cliente_documento" => $e["cliente_factura_id"] === null ? null : $this->cliente((int) $e["cliente_factura_id"]),
            "estacion_origen_id" => $e["origen_id"],
            "estacion_destino_id" => $e["destino_id"],
            "tipo_documento_id" => Traduccion::tipoDocumentoEncomienda($modalidad),
            "precioCalculadoMonedaBase" => $total,
            "precioCalculado" => $total,
            "moneda_id" => $e["moneda_id"] ?? Traduccion::GTQ,
            "tipo_pago_id" => $e["tipo_pago_id"],
            "por_cobrar_sin_facturar" => $modalidad === ModalidadEncomienda::POR_COBRAR,
            "codigo" => "FDN" . $e["id"],
            "observacion" => mb_substr(trim("Recibida en FDN. " . ($e["observacion"] ?? "")), 0, 4000),
            "primera_salida_id" => $e["salida_legado"],
            "boleto_id" => $e["boleto_legado"],
            "empresa_id" => $e["empresa_id"],
            "estacion_creacion_id" => $e["estacion_id"] ?? $e["origen_id"],
            "fecha_creacion" => new \DateTimeImmutable((string) $e["creada"]),
            "usuario_creacion_id" => $usuario,
        ]);
        $this->legado->insertar("encomienda_ruta", [
            "encomienda_id" => $legado,
            "posicion" => 1,
            "ruta_codigo" => $e["ruta_legado"],
            "estacion" => $e["destino_id"],
        ]);
        // La bitácora del legado empieza siempre en recibida.
        $this->eventoEncomienda($legado, 1, (int) ($e["estacion_id"] ?? $e["origen_id"]), $usuario, null, null);

        return $legado;
    }

    /**
     * Si el estado de aquí no es el del último evento allá, agrega el evento
     * (con la salida en la que va o la estación donde está).
     *
     * @param array<string, mixed> $e
     */
    private function estadoEncomienda(int $legado, array $e): void
    {
        $estado = EstadoEncomienda::from((string) $e["estado"]);
        $codigo = Traduccion::estadoEncomienda($estado);
        $ultimo = $this->legado->valor(
            "SELECT eb.estado_id FROM encomienda en JOIN encomienda_bitacora eb ON eb.id = en.ultima_bitacora_id WHERE en.id = :id",
            ["id" => $legado],
        );
        if ($ultimo !== null && (int) $ultimo === $codigo) {
            return;
        }
        $estacion = $e["ubicacion_id"] ?? ($estado === EstadoEncomienda::ENTREGADA ? $e["destino_id"] : null) ?? $e["estacion_id"] ?? $e["origen_id"];
        $usuario = $this->usuario($e["usuario_id"] === null ? null : (int) $e["usuario_id"])
            ?? throw new \RuntimeException("El legado exige un usuario: configure SINCRONIZACION_USUARIO_LEGADO.");
        $this->eventoEncomienda(
            $legado,
            $codigo,
            (int) $estacion,
            $usuario,
            $estado->aBordo() || $estado === EstadoEncomienda::DESEMBARCADA ? $e["salida_legado"] : null,
            $estado === EstadoEncomienda::ENTREGADA && $e["receptor_id"] !== null ? $this->cliente((int) $e["receptor_id"]) : null,
        );
        $this->legado->ejecutar(
            "UPDATE encomienda SET primera_salida_id = COALESCE(primera_salida_id, :salida), transito = :transito,
                    observacion = CASE WHEN :anulada = 1 THEN LEFT(CONCAT(observacion, ' Anulada en FDN: ', :motivo), 4000) ELSE observacion END
              WHERE id = :id",
            [
                "salida" => $e["salida_legado"],
                "transito" => $estado === EstadoEncomienda::TRANSITO,
                "anulada" => $estado === EstadoEncomienda::ANULADA ? 1 : 0,
                "motivo" => (string) ($e["motivo_anulacion"] ?? ""),
                "id" => $legado,
            ],
        );
    }

    private function eventoEncomienda(int $encomienda, int $estado, int $estacion, int $usuario, mixed $salida, ?int $cliente): void
    {
        $evento = $this->legado->insertar("encomienda_bitacora", [
            "encomienda_id" => $encomienda,
            "estacion" => $estacion,
            "fecha" => new \DateTimeImmutable(),
            "estado_id" => $estado,
            "usuario_id" => $usuario,
            "salida_id" => $salida,
            "cliente_id" => $cliente,
        ]);
        $this->legado->ejecutar("UPDATE encomienda SET ultima_bitacora_id = :ev WHERE id = :id", ["ev" => $evento, "id" => $encomienda]);
    }
}
