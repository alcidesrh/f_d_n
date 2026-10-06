<?php

declare(strict_types=1);

namespace App\PruebaLegado;

use App\Entity\Cliente;
use App\Entity\TipoPago;
use App\Venta\Excepcion\VentaRechazada;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * TEMPORAL (ver `Legado`). La pantalla de venta contra el sistema anterior: salidas del día,
 * croquis con la ocupación real, cotización con `tarifas_boleto` y una venta SIMULADA que no
 * escribe nada (ni en el legado ni en la base nueva). Devuelve las mismas formas JSON que
 * `ConsultaVenta`/`VentaController` para que la pantalla no distinga el origen.
 *
 * Los ids de asiento y de estación son los del legado. Un "trayecto" vendible es un par
 * (sube, baja) de paradas de la ruta: su id es `sube * 1_000_000 + baja`.
 */
final class VentaLegado
{
    /** `boleto_estado`: emitido, chequeado, en tránsito (los que ocupan asiento). */
    private const VIGENTES = "1, 2, 3";
    private const ESTADOS_SALIDA = [1 => "programada", 2 => "abordando", 3 => "iniciada", 4 => "cancelada", 5 => "finalizada"];
    private const FACTOR = 1_000_000;

    private const SALIDA_SQL = "SELECT s.id, s.fecha, s.estado_id, s.empresa_id, COALESCE(NULLIF(e.alias, ''), e.nombre) AS empresa,
            s.bus_codigo, COALESCE(s.tipo_bus_id, b.tipo_id) AS tipo_bus_id, bc.nombre AS gama, bt.clase_id AS bus_clase_id,
            i.ruta_codigo, r.estacion_origen_id AS origen_id, r.estacion_destino_id AS destino_id, eo.nombre AS origen, ed.nombre AS destino,
            (SELECT COUNT(*) FROM bus_asiento ba WHERE ba.tipoBus_id = COALESCE(s.tipo_bus_id, b.tipo_id)) AS capacidad,
            (SELECT COUNT(DISTINCT bo.asiento_bus_id) FROM boleto bo WHERE bo.salida_id = s.id AND bo.estado_id IN (1, 2, 3)) AS vendidos
        FROM salida s
        JOIN itineario i ON i.id = s.itinerario_id
        JOIN ruta r ON r.codigo = i.ruta_codigo
        JOIN estacion eo ON eo.id = r.estacion_origen_id
        JOIN estacion ed ON ed.id = r.estacion_destino_id
        LEFT JOIN empresa e ON e.id = s.empresa_id
        LEFT JOIN bus b ON b.codigo = s.bus_codigo
        LEFT JOIN bus_tipo bt ON bt.id = COALESCE(s.tipo_bus_id, b.tipo_id)
        LEFT JOIN bus_clase bc ON bc.id = bt.clase_id";

    public function __construct(
        private readonly Legado $db,
        private readonly EntityManagerInterface $em,
    ) {}

    /** @return list<array{id: int, nombre: string, direccion: ?string, departamento: ?string}> */
    public function estaciones(): array
    {
        return array_map(static fn(array $f) => [
            "id" => (int) $f["id"],
            "nombre" => (string) $f["nombre"],
            "direccion" => $f["direccion"] !== null ? trim((string) $f["direccion"]) : null,
            "departamento" => $f["departamento"],
        ], $this->db->filas(
            "SELECT e.id, e.nombre, e.direccion, d.nombre AS departamento
             FROM estacion e LEFT JOIN departamento d ON d.id = e.departamento_id
             WHERE e.activo = 1 ORDER BY e.nombre",
        ));
    }

    /** @return list<array<string, mixed>> */
    public function salidas(\DateTimeImmutable $dia, ?int $estacionId): array
    {
        $filas = $this->db->filas(
            self::SALIDA_SQL . " WHERE s.fecha >= :desde AND s.fecha < :hasta ORDER BY s.fecha, s.id",
            ["desde" => $dia->format("Y-m-d 00:00:00"), "hasta" => $dia->modify("+1 day")->format("Y-m-d 00:00:00")],
        );
        $paradas = $this->paradasDeRutas($filas);

        $resultado = [];
        foreach ($filas as $f) {
            $deRuta = $paradas[$f["ruta_codigo"]];
            if ($estacionId !== null) {
                $pos = array_search($estacionId, $deRuta, true);
                if ($pos === false || $pos >= count($deRuta) - 1) {
                    continue;
                }
            }
            $resultado[] = $this->resumen($f);
        }

        return $resultado;
    }

    /** @return array<string, mixed> */
    public function detalle(int $salidaId): array
    {
        $f = $this->salida($salidaId);
        $paradas = $this->paradasDeRutas([$f])[$f["ruta_codigo"]];
        $nombres = $this->nombres($paradas);
        $croquis = $this->croquis((int) $f["tipo_bus_id"]);
        $clasesBus = array_values(array_unique(array_column(array_filter($croquis, static fn(array $e) => $e["tipo"] === "asiento"), "clase")));
        $tarifas = $this->tarifas($paradas, $f);

        $trayectos = [];
        foreach ($paradas as $i => $sube) {
            foreach (array_slice($paradas, $i + 1) as $j => $baja) {
                $clases = array_values(array_filter($clasesBus, static fn(string $c) => isset($tarifas["$sube-$baja"][$c])));
                sort($clases);
                if ($clases !== []) {
                    $trayectos[] = ["id" => self::trayecto($sube, $baja), "origen" => $sube, "destino" => $baja, "completo" => $i === 0 && $i + 1 + $j === count($paradas) - 1, "clases" => $clases];
                }
            }
        }

        $salida = new \DateTimeImmutable((string) $f["fecha"], new \DateTimeZone(Legado::ZONA));

        return [
            ...$this->resumen($f),
            "paradas" => array_map(static fn(int $id, int $pos) => [
                "id" => $id,
                "nombre" => $nombres[$id] ?? null,
                "direccion" => null,
                "posicion" => $pos,
                "hora" => null,
            ], $paradas, array_keys($paradas)),
            "trayectos" => $trayectos,
            "croquis" => $croquis,
            "cierreEnLinea" => $salida->modify("-60 minutes")->format(DATE_ATOM),
            // Nadie publica en este tópico: la ocupación se lee de nuevo al vender o cambiar de tramo.
            "topico" => "/prueba-legado/salidas/$salidaId/ocupacion",
        ];
    }

    /** @return list<array{asiento: int, estado: string, canal: ?string, sinCobro: ?string}> */
    public function ocupacion(int $salidaId, ?int $trayectoId): array
    {
        $f = $this->salida($salidaId);
        $paradas = $this->paradasDeRutas([$f])[$f["ruta_codigo"]];
        [$desde, $hasta] = $this->tramo($paradas, $trayectoId);

        $boletos = $this->db->filas(
            "SELECT bo.asiento_bus_id, bo.estacion_origen_id AS o, bo.estacion_destino_id AS d, bo.pagina_web_reserva_id,
                    ec.tipoEstacion_id AS tipo_estacion, bo.voucher_agencia_id, bo.voucher_estacion_id, bo.voucher_internet_id, bo.autorizacion_cortesia_id
             FROM boleto bo LEFT JOIN estacion ec ON ec.id = bo.estacion_creacion_id
             WHERE bo.salida_id = " . $salidaId . " AND bo.estado_id IN (" . self::VIGENTES . ")",
        );

        $lista = [];
        foreach ($boletos as $b) {
            $o = array_search((int) $b["o"], $paradas, true);
            $d = array_search((int) $b["d"], $paradas, true);
            // Un boleto cuyas estaciones no están en la ruta se toma como todo el viaje.
            $choca = $o === false || $d === false || ($o < $hasta && $desde < $d);
            if (!$choca) {
                continue;
            }
            $asiento = (int) $b["asiento_bus_id"];
            $lista[$asiento] ??= [
                "asiento" => $asiento,
                "estado" => "vendido",
                "canal" => $b["pagina_web_reserva_id"] !== null ? "web" : ((int) $b["tipo_estacion"] === 4 ? "agencia" : "estacion"),
                "sinCobro" => ($b["voucher_agencia_id"] ?? $b["voucher_estacion_id"] ?? $b["voucher_internet_id"]) !== null ? "voucher" : ($b["autorizacion_cortesia_id"] !== null ? "cortesia" : null),
            ];
        }

        return array_values($lista);
    }

    /**
     * @param list<int> $asientos ids de `bus_asiento`
     *
     * @return array<string, mixed>
     */
    public function cotizar(int $salidaId, ?int $trayectoId, array $asientos, bool $trayectoCompleto, bool $cortesia): array
    {
        $f = $this->salida($salidaId);
        $paradas = $this->paradasDeRutas([$f])[$f["ruta_codigo"]];
        if ($asientos === []) {
            throw new VentaRechazada("Elija al menos un asiento.", "sin_asientos");
        }

        [$desde, $hasta] = $trayectoCompleto ? [0, count($paradas) - 1] : $this->tramo($paradas, $trayectoId);
        [$sube, $baja] = [$paradas[$desde], $paradas[$hasta]];
        $tarifas = $this->tarifas([$sube, $baja], $f)["$sube-$baja"] ?? [];

        $porId = [];
        foreach ($this->db->filas("SELECT id, numero, clase_id FROM bus_asiento WHERE tipoBus_id = " . (int) $f["tipo_bus_id"] . " AND id IN (" . Legado::enteros($asientos) . ")") as $a) {
            $porId[(int) $a["id"]] = $a;
        }

        $lineas = [];
        $total = 0;
        foreach ($asientos as $id) {
            $a = $porId[$id] ?? throw new VentaRechazada("El asiento $id no es de este bus.", "asiento_invalido");
            $clase = self::clase($a["clase_id"]);
            $centavos = $tarifas[$clase] ?? throw new VentaRechazada(sprintf("No hay tarifa de clase %s para ese tramo.", $clase), "sin_tarifa");
            $centavos = $cortesia ? 0 : $centavos;
            $total += $centavos;
            $lineas[] = ["asiento" => $id, "numero" => (int) $a["numero"], "clase" => $clase, "precio" => self::importe($centavos), "tarifa" => null];
        }

        return ["asientos" => $lineas, "total" => self::importe($total)];
    }

    /**
     * Simula una venta: valida y cotiza como la real y arma el comprobante con una factura
     * ficticia. No escribe nada: los asientos siguen libres.
     *
     * @param array<string, mixed> $datos cuerpo de `POST /venta/ventas`
     *
     * @return array<string, mixed>
     */
    public function simularVenta(array $datos, string $vendedor): array
    {
        $salidaId = (int) ($datos["salida"] ?? 0);
        $trayectoId = isset($datos["trayecto"]) ? (int) $datos["trayecto"] : null;
        $asientos = array_values(array_unique(array_map(static fn(array $a) => (int) $a["asiento"], (array) ($datos["asientos"] ?? []))));
        $cortesia = (bool) ($datos["cortesia"] ?? false);

        $f = $this->salida($salidaId);
        $paradas = $this->paradasDeRutas([$f])[$f["ruta_codigo"]];
        $cotizacion = $this->cotizar($salidaId, $trayectoId, $asientos, (bool) ($datos["cobrarTrayectoCompleto"] ?? false), $cortesia);

        $ocupados = array_column($this->ocupacion($salidaId, $trayectoId), "asiento");
        if ($tomados = array_values(array_intersect($asientos, $ocupados))) {
            throw new VentaRechazada("Algún asiento elegido ya no está disponible.", "asientos_no_disponibles", 409, ["asientos" => $tomados]);
        }

        $cliente = $this->em->find(Cliente::class, (int) ($datos["cliente"] ?? 0)) ?? throw new VentaRechazada("Elija el cliente.", "cliente_invalido");
        $tipoPago = isset($datos["tipoPago"]) ? $this->em->find(TipoPago::class, (int) $datos["tipoPago"]) : null;
        $pasajeros = [];
        foreach ((array) ($datos["asientos"] ?? []) as $a) {
            $p = isset($a["cliente"]) ? $this->em->find(Cliente::class, (int) $a["cliente"]) : null;
            $pasajeros[(int) $a["asiento"]] = $p?->getNombreCompleto();
        }

        [$desde, $hasta] = $this->tramo($paradas, $trayectoId);
        $nombres = $this->nombres([$paradas[$desde], $paradas[$hasta]]);
        $estacion = isset($datos["estacion"]) ? $this->db->fila("SELECT nombre, direccion FROM estacion WHERE id = " . (int) $datos["estacion"]) : null;
        $empresa = $f["empresa_id"] !== null ? $this->db->fila("SELECT nombre, nit, direccion, telefonos FROM empresa WHERE id = " . (int) $f["empresa_id"]) : null;
        $ahora = Legado::ahora();
        $id = random_int(90_000_000, 99_999_999);

        return [
            "id" => $id,
            "token" => $datos["token"] ?? null,
            "canal" => "estacion",
            "estado" => "confirmada",
            "estadoFacturacion" => $cortesia ? "no_aplica" : "certificada",
            "numeroAcceso" => null,
            "cortesia" => $cortesia,
            "creada" => $ahora->format(DATE_ATOM),
            "codigoBarras" => sprintf("%08d", $id),
            "empresa" => $empresa === null ? null : ["nombre" => $empresa["nombre"], "nit" => $empresa["nit"], "direccion" => $empresa["direccion"], "telefono" => self::telefonos($empresa["telefonos"])],
            "estacion" => $estacion === null ? null : ["nombre" => $estacion["nombre"], "direccion" => $estacion["direccion"]],
            "agencia" => null,
            "vendedor" => $vendedor,
            "factura" => $cortesia ? null : [
                "numero" => random_int(100_000, 999_999),
                "serie" => "SIMULADA",
                "uuid" => strtoupper(Uuid::v4()->toRfc4122()),
                "fechaCertificacion" => $ahora->format(DATE_ATOM),
                "certificador" => "SIMULACIÓN (no se guardó nada)",
                "certificadorNit" => null,
                "receptorNit" => $cliente->getNit() ?: "CF",
                "receptorNombre" => $cliente->getNombreCompleto(),
                "urlPdf" => null,
            ],
            "cliente" => ["nombre" => $cliente->getNombreCompleto(), "nit" => $cliente->getNit() ?: "CF", "email" => $cliente->getEmail()],
            "salida" => ["id" => $salidaId, "salida" => Legado::iso((string) $f["fecha"]), "salidaOrigen" => Legado::iso((string) $f["fecha"]), "bus" => $f["bus_codigo"]],
            "origen" => ["nombre" => $nombres[$paradas[$desde]] ?? "?", "direccion" => null],
            "destino" => ["nombre" => $nombres[$paradas[$hasta]] ?? "?", "direccion" => null],
            "boletos" => array_map(static fn(array $l, int $i) => [
                "id" => $id * 100 + $i,
                "asiento" => $l["numero"],
                "clase" => $l["clase"],
                "pasajero" => $pasajeros[$l["asiento"]] ?? null,
                "precio" => $l["precio"],
                "observacion" => $datos["observacion"] ?? null,
                "estado" => "emitido",
            ], $cotizacion["asientos"], array_keys($cotizacion["asientos"])),
            "total" => $cotizacion["total"],
            "tipoPago" => $tipoPago?->getNombre(),
        ];
    }

    /** @return array<string, mixed> */
    private function salida(int $id): array
    {
        return $this->db->fila(self::SALIDA_SQL . " WHERE s.id = " . $id)
            ?? throw new VentaRechazada("La salida no existe en el legado.", "no_encontrado", 404);
    }

    /** @param array<string, mixed> $f */
    private function resumen(array $f): array
    {
        $paradaIds = [(int) $f["origen_id"], (int) $f["destino_id"]];

        return [
            "id" => (int) $f["id"],
            "salida" => Legado::iso((string) $f["fecha"]),
            "salidaEstacion" => null,
            "estado" => self::ESTADOS_SALIDA[(int) $f["estado_id"]] ?? "programada",
            "empresa" => $f["empresa_id"] === null ? null : ["id" => (int) $f["empresa_id"], "nombre" => (string) $f["empresa"]],
            "bus" => $f["bus_codigo"] === null ? null : ["id" => (int) $f["bus_codigo"], "codigo" => (string) $f["bus_codigo"], "gama" => $f["gama"]],
            "trayecto" => [
                "id" => self::trayecto(...$paradaIds),
                "origen" => ["id" => $paradaIds[0], "nombre" => (string) $f["origen"]],
                "destino" => ["id" => $paradaIds[1], "nombre" => (string) $f["destino"]],
            ],
            "vendidos" => (int) $f["vendidos"],
            "capacidad" => $f["bus_codigo"] === null ? null : (int) $f["capacidad"],
        ];
    }

    /**
     * Paradas ordenadas de cada ruta: origen, intermedias por `posicion`, destino.
     *
     * @param list<array<string, mixed>> $salidas filas con `ruta_codigo`, `origen_id`, `destino_id`
     *
     * @return array<string, list<int>> por código de ruta
     */
    private function paradasDeRutas(array $salidas): array
    {
        $codigos = array_values(array_unique(array_column($salidas, "ruta_codigo")));
        if ($codigos === []) {
            return [];
        }
        $items = [];
        foreach ($this->db->filas("SELECT ruta_codigo, estacion_id FROM ruta_estacion_item WHERE ruta_codigo IN ('" . implode("','", array_map(static fn(string $c) => str_replace("'", "''", $c), $codigos)) . "') ORDER BY ruta_codigo, posicion") as $i) {
            $items[$i["ruta_codigo"]][] = (int) $i["estacion_id"];
        }

        $resultado = [];
        foreach ($salidas as $f) {
            $resultado[$f["ruta_codigo"]] ??= array_values(array_unique([(int) $f["origen_id"], ...($items[$f["ruta_codigo"]] ?? []), (int) $f["destino_id"]]));
        }

        return $resultado;
    }

    /** @return array<int, string> */
    private function nombres(array $ids): array
    {
        $nombres = [];
        foreach ($this->db->filas("SELECT id, nombre FROM estacion WHERE id IN (" . Legado::enteros($ids) . ")") as $e) {
            $nombres[(int) $e["id"]] = (string) $e["nombre"];
        }

        return $nombres;
    }

    /** @return list<array<string, mixed>> */
    private function croquis(int $tipoBusId): array
    {
        $celda = static fn(array $f) => [
            "planta" => filter_var($f["nivel2"], FILTER_VALIDATE_BOOL) ? 2 : 1,
            "fila" => intdiv((int) $f["coordenadaY"], 50) + 1,
            "columna" => intdiv((int) $f["coordenadaX"], 50) + 1,
        ];

        $elementos = [];
        foreach ($this->db->filas("SELECT id, numero, clase_id, nivel2, coordenadaX, coordenadaY FROM bus_asiento WHERE tipoBus_id = " . $tipoBusId) as $a) {
            $elementos[] = ["tipo" => "asiento", "id" => (int) $a["id"], "numero" => (int) $a["numero"], "clase" => self::clase($a["clase_id"]), ...$celda($a)];
        }
        foreach ($this->db->filas("SELECT id, tipo_id, nivel2, coordenadaX, coordenadaY FROM bus_senal WHERE tipoBus_id = " . $tipoBusId) as $s) {
            $elementos[] = ["tipo" => (int) $s["tipo_id"] === 2 ? "chofer" : "puerta", "id" => null, ...$celda($s)];
        }

        return $elementos;
    }

    /**
     * Tarifa vigente por tramo y clase de asiento, en centavos: `["sube-baja" => ["A" => 6000, ...]]`.
     * Entre las vigentes gana la que coincide con la clase del bus, luego la que tiene horario,
     * luego la más reciente (aproxima la prioridad del sistema anterior).
     *
     * @param list<int>            $paradas
     * @param array<string, mixed> $salida
     *
     * @return array<string, array<string, int>>
     */
    private function tarifas(array $paradas, array $salida): array
    {
        $ids = Legado::enteros($paradas);
        $claseBus = $salida["bus_clase_id"] !== null ? (int) $salida["bus_clase_id"] : null;
        $filas = $this->db->filas(
            "SELECT o, d, clase, valor, cb, hi, hf, fe FROM (
                SELECT t.estacion_origen_id AS o, t.estacion_destino_id AS d, t.clase_asiento_id AS clase, t.tarifaValor AS valor,
                       t.clase_bus_id AS cb, t.horaInicialSalida AS hi, t.horaFinalSalida AS hf, t.fechaEfectividad AS fe,
                       ROW_NUMBER() OVER (PARTITION BY t.estacion_origen_id, t.estacion_destino_id, t.clase_asiento_id, t.clase_bus_id, t.horaInicialSalida, t.horaFinalSalida
                                          ORDER BY t.fechaEfectividad DESC, t.id DESC) AS rn
                FROM tarifas_boleto t
                WHERE t.estacion_origen_id IN ($ids) AND t.estacion_destino_id IN ($ids) AND t.fechaEfectividad <= :ahora"
                . ($claseBus === null ? " AND t.clase_bus_id IS NULL" : " AND (t.clase_bus_id IS NULL OR t.clase_bus_id = $claseBus)") . "
            ) x WHERE rn = 1",
            ["ahora" => Legado::ahora()->format("Y-m-d H:i:s")],
        );

        $hora = substr((string) $salida["fecha"], 11, 8);
        $mejor = [];
        foreach ($filas as $t) {
            $conHorario = $t["hi"] !== null && $t["hf"] !== null;
            if ($conHorario) {
                $hi = substr((string) $t["hi"], 0, 8);
                $hf = substr((string) $t["hf"], 0, 8);
                if (!($hi <= $hf ? ($hora >= $hi && $hora <= $hf) : ($hora >= $hi || $hora <= $hf))) {
                    continue;
                }
            }
            $clave = $t["o"] . "-" . $t["d"];
            $clase = self::clase($t["clase"]);
            $puntos = [($t["cb"] !== null ? 2 : 0) + ($conHorario ? 1 : 0), (string) $t["fe"]];
            if (!isset($mejor[$clave][$clase]) || $puntos > $mejor[$clave][$clase][0]) {
                $mejor[$clave][$clase] = [$puntos, (int) round((float) $t["valor"] * 100)];
            }
        }

        return array_map(static fn(array $porClase) => array_map(static fn(array $m) => $m[1], $porClase), $mejor);
    }

    /**
     * Posiciones [desde, hasta] de un trayecto vendible dentro de las paradas (por defecto, el viaje completo).
     *
     * @param list<int> $paradas
     *
     * @return array{int, int}
     */
    private function tramo(array $paradas, ?int $trayectoId): array
    {
        if ($trayectoId === null) {
            return [0, count($paradas) - 1];
        }
        $desde = array_search(intdiv($trayectoId, self::FACTOR), $paradas, true);
        $hasta = array_search($trayectoId % self::FACTOR, $paradas, true);
        if ($desde === false || $hasta === false || $desde >= $hasta) {
            throw new VentaRechazada("El trayecto no pertenece a la salida.", "trayecto_invalido");
        }

        return [$desde, $hasta];
    }

    private static function trayecto(int $sube, int $baja): int
    {
        return $sube * self::FACTOR + $baja;
    }

    /** Como en la migración: la clase 2 del legado es la B; todo lo demás, A. */
    private static function clase(mixed $claseId): string
    {
        return (int) $claseId === 2 ? "B" : "A";
    }

    /** `empresa.telefonos` es un arreglo PHP serializado. */
    private static function telefonos(mixed $valor): ?string
    {
        $lista = is_string($valor) ? @unserialize($valor, ["allowed_classes" => false]) : false;

        return is_array($lista) ? implode(" / ", $lista) : ($valor !== null ? (string) $valor : null);
    }

    /** @return array{centavos: int, moneda: string, texto: string} */
    private static function importe(int $centavos): array
    {
        return ["centavos" => $centavos, "moneda" => "GTQ", "texto" => "Q " . number_format($centavos / 100, 2)];
    }
}
