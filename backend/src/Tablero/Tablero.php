<?php

declare(strict_types=1);

namespace App\Tablero;

/**
 * Arma el tablero de gestión a partir de lo que lee `ConsultaTablero`. Puro:
 * todas las reglas (comparaciones, ocupación, señales de rutas, alertas)
 * viven aquí y se prueban sin base de datos. Importes en centavos.
 *
 * Criterios: la venta se cuenta por la fecha en que se vendió (es caja); la
 * ocupación, por la fecha de la salida y solo de las que ya salieron (las
 * futuras están a medio vender y se miran aparte, en "venta anticipada").
 */
final class Tablero
{
    /** Ocupación desde la que una ruta pide más frecuencia. */
    public const SATURADA = 0.85;
    /** Ocupación por debajo de la que una ruta sobra frecuencia. */
    public const BAJA = 0.35;
    /** Salidas mínimas para juzgar una ruta. */
    public const MIN_SALIDAS_RUTA = 3;
    /** Una salida que llegó hace más de esto y no se finalizó queda "sin cerrar". */
    public const HORAS_SIN_CERRAR = 2;
    /** Días de venta que debe cubrir el saldo de una agencia. */
    public const DIAS_COBERTURA = 3;

    private const CANALES = ["estacion", "agencia", "web"];
    /** Estados de un boleto que puede tener una salida de hoy fuera de la web (`pospuesto` es solo web). */
    private const ESTADOS_BOLETO = ["emitido", "chequeado", "transito", "finalizado", "anulado", "reasignado", "cancelado"];
    /** Estados de boleto de los que "Operación de hoy" muestra quién más los hizo. */
    private const ESTADOS_RANKING = ["anulado", "reasignado", "cancelado"];
    /** Usuarios por estado en "Operación de hoy". */
    public const USUARIOS_TOP = 3;

    /**
     * @param list<array{actual: bool, t: string, canal: string, ingresos: int, boletos: int}> $ventas cubos (hora o día) por canal
     * @param array{actual: array{anulados: int, montoAnulado: int, cortesias: int, valorCortesias: int}, anterior: array{anulados: int, montoAnulado: int, cortesias: int, valorCortesias: int}} $incidencias
     * @param list<SalidaTablero> $salidas desde `Periodo::inicioLectura()` hasta una semana adelante
     * @param list<array{id: int, fecha: string, ruta: string, estado: string, llegada: string}> $sinCerrar
     * @param list<array{tipo: string, nombre: string, ingresos: int, boletos: int, anterior: int}> $puntos
     * @param list<array{id: int, nombre: string, saldo: int, moneda: string, ventas7: int}> $agencias activas
     * @param array{pendientes: int, conError: int, masAntigua: ?string} $facturacion
     * @param array{pendientes: int, importe: int} $depositos
     * @param array{buses: int, vencidas: list<array{id: int, codigo: string, vence: string}>, porVencer: list<array{id: int, codigo: string, vence: string}>} $flota
     * @param list<array{usuarioId: ?int, nombre: ?string, usuario: ?string, estado: string, boletos: int}> $boletosHoy boletos de las salidas de hoy sin la web, por estado y vendedor
     * @param array{detenidas: list<string>, fallidos: int} $programador tareas sin corrida buena en su tolerancia y mensajes que agotaron reintentos (ADR-044)
     */
    public function __construct(
        private readonly Periodo $periodo,
        private readonly array $ventas,
        private readonly array $incidencias,
        private readonly array $salidas,
        private readonly array $sinCerrar,
        private readonly array $puntos,
        private readonly array $agencias,
        private readonly array $facturacion,
        private readonly array $depositos,
        private readonly array $flota,
        private readonly array $boletosHoy = [],
        private readonly array $programador = ["detenidas" => [], "fallidos" => 0],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $p = $this->periodo;

        return [
            "generado" => $p->ahora->format(DATE_ATOM),
            "periodo" => [
                "dias" => $p->dias,
                "desde" => $p->inicio->format("Y-m-d"),
                "hasta" => $p->ahora->format("Y-m-d"),
                "anteriorDesde" => $p->inicioAnterior->format("Y-m-d"),
                "anteriorHasta" => $p->finAnterior->format("Y-m-d"),
                "rotuloAnterior" => $p->rotuloAnterior(),
                "porHora" => $p->porHora(),
            ],
            "kpis" => $this->kpis(),
            "serie" => $this->serie(),
            "canales" => $this->canales(),
            "operacionHoy" => $this->operacionHoy(),
            "rutas" => $this->rutas(),
            "demanda" => $this->demanda(),
            "anticipada" => $this->anticipada(),
            "puntos" => $this->puntos(),
            "agencias" => $this->agencias(),
            "flota" => $this->flotaResumen(),
            "alertas" => $this->alertas(),
        ];
    }

    /** @return array<string, array{actual: int|float|null, anterior: int|float|null}> */
    public function kpis(): array
    {
        $ingresos = ["actual" => 0, "anterior" => 0];
        $boletos = ["actual" => 0, "anterior" => 0];
        foreach ($this->ventas as $v) {
            $k = $v["actual"] ? "actual" : "anterior";
            $ingresos[$k] += $v["ingresos"];
            $boletos[$k] += $v["boletos"];
        }
        $ticket = fn(string $k) => $boletos[$k] > 0 ? intdiv($ingresos[$k], $boletos[$k]) : null;
        $tasaAnulacion = function (string $k) use ($boletos): ?float {
            $anulados = $this->incidencias[$k]["anulados"];
            $total = $boletos[$k] + $anulados;

            return $total > 0 ? round($anulados / $total, 4) : null;
        };

        $salidas = ["actual" => $this->salidasSalieron(true), "anterior" => $this->salidasSalieron(false)];
        $noPresentados = function (array $lista): ?float {
            $presentes = array_sum(array_map(static fn(SalidaTablero $s) => $s->boletos, $lista));
            $ausentes = array_sum(array_map(static fn(SalidaTablero $s) => $s->noPresentados, $lista));

            return $presentes + $ausentes > 0 ? round($ausentes / ($presentes + $ausentes), 4) : null;
        };
        $porSalida = static function (array $lista): ?int {
            $n = count(array_filter($lista, static fn(SalidaTablero $s) => !$s->cancelada()));

            return $n > 0 ? intdiv(array_sum(array_map(static fn(SalidaTablero $s) => $s->ingresos, $lista)), $n) : null;
        };
        $canceladas = static fn(array $lista) => count(array_filter($lista, static fn(SalidaTablero $s) => $s->cancelada()));

        return [
            "ingresos" => $ingresos,
            "boletos" => $boletos,
            "ticketPromedio" => ["actual" => $ticket("actual"), "anterior" => $ticket("anterior")],
            "ocupacion" => ["actual" => self::ocupacionDe($salidas["actual"]), "anterior" => self::ocupacionDe($salidas["anterior"])],
            "ingresoPorSalida" => ["actual" => $porSalida($salidas["actual"]), "anterior" => $porSalida($salidas["anterior"])],
            "salidas" => [
                "actual" => count($salidas["actual"]) - $canceladas($salidas["actual"]),
                "anterior" => count($salidas["anterior"]) - $canceladas($salidas["anterior"]),
            ],
            "salidasCanceladas" => ["actual" => $canceladas($salidas["actual"]), "anterior" => $canceladas($salidas["anterior"])],
            "anulados" => ["actual" => $this->incidencias["actual"]["anulados"], "anterior" => $this->incidencias["anterior"]["anulados"]],
            "montoAnulado" => ["actual" => $this->incidencias["actual"]["montoAnulado"], "anterior" => $this->incidencias["anterior"]["montoAnulado"]],
            "tasaAnulacion" => ["actual" => $tasaAnulacion("actual"), "anterior" => $tasaAnulacion("anterior")],
            "tasaNoPresentados" => ["actual" => $noPresentados($salidas["actual"]), "anterior" => $noPresentados($salidas["anterior"])],
            "cortesias" => ["actual" => $this->incidencias["actual"]["cortesias"], "anterior" => $this->incidencias["anterior"]["cortesias"]],
            "valorCortesias" => ["actual" => $this->incidencias["actual"]["valorCortesias"], "anterior" => $this->incidencias["anterior"]["valorCortesias"]],
        ];
    }

    /**
     * Un cubo por hora (hoy) o por día del periodo actual, con lo vendido por
     * canal y el total del cubo equivalente del periodo anterior. Los cubos
     * que aún no llegan quedan con `actual` en null (no son ceros).
     *
     * @return list<array{t: string, actual: ?array{estacion: int, agencia: int, web: int, total: int, boletos: int}, anterior: array{total: int, boletos: int}}>
     */
    public function serie(): array
    {
        $p = $this->periodo;
        $formato = $p->porHora() ? "Y-m-d H:00" : "Y-m-d";
        $paso = $p->porHora() ? "+1 hour" : "+1 day";
        $fin = $p->hoy()->modify("+1 day");

        $cubos = [];
        for ($t = $p->inicio; $t < $fin; $t = $t->modify($paso)) {
            $cubos[$t->format($formato)] = [
                "t" => $t->format($formato),
                "actual" => $t <= $p->ahora ? ["estacion" => 0, "agencia" => 0, "web" => 0, "total" => 0, "boletos" => 0] : null,
                "anterior" => ["total" => 0, "boletos" => 0],
            ];
        }
        foreach ($this->ventas as $v) {
            $t = new \DateTimeImmutable($v["t"]);
            if ($v["actual"]) {
                $llave = $t->format($formato);
                if (!isset($cubos[$llave]) || $cubos[$llave]["actual"] === null) {
                    continue;
                }
                $canal = in_array($v["canal"], self::CANALES, true) ? $v["canal"] : "estacion";
                $cubos[$llave]["actual"][$canal] += $v["ingresos"];
                $cubos[$llave]["actual"]["total"] += $v["ingresos"];
                $cubos[$llave]["actual"]["boletos"] += $v["boletos"];
            } else {
                $llave = $p->alActual($t)->format($formato);
                if (isset($cubos[$llave])) {
                    $cubos[$llave]["anterior"]["total"] += $v["ingresos"];
                    $cubos[$llave]["anterior"]["boletos"] += $v["boletos"];
                }
            }
        }

        return array_values($cubos);
    }

    /** @return list<array{canal: string, ingresos: int, boletos: int, anterior: int}> */
    public function canales(): array
    {
        $r = [];
        foreach (self::CANALES as $c) {
            $r[$c] = ["canal" => $c, "ingresos" => 0, "boletos" => 0, "anterior" => 0];
        }
        foreach ($this->ventas as $v) {
            $c = in_array($v["canal"], self::CANALES, true) ? $v["canal"] : "estacion";
            if ($v["actual"]) {
                $r[$c]["ingresos"] += $v["ingresos"];
                $r[$c]["boletos"] += $v["boletos"];
            } else {
                $r[$c]["anterior"] += $v["ingresos"];
            }
        }

        return array_values($r);
    }

    /**
     * Salidas de hoy y lo vendido en ellas, y los boletos de hoy por estado
     * con quiénes más los anularon, reasignaron o cancelaron.
     *
     * @return array<string, mixed>
     */
    public function operacionHoy(): array
    {
        $p = $this->periodo;
        $manana = $p->hoy()->modify("+1 day");
        $hoy = array_values(array_filter($this->salidas, static fn(SalidaTablero $s) => $s->fecha >= $p->hoy() && $s->fecha < $manana));
        $vivas = array_filter($hoy, static fn(SalidaTablero $s) => !$s->cancelada());

        return [
            "total" => count($hoy),
            "boletos" => $this->boletosDeHoy(),
            "asientosVendidos" => array_sum(array_map(static fn(SalidaTablero $s) => $s->boletos, $vivas)),
            "ocupacion" => self::ocupacionDe(array_values($vivas)),
        ];
    }

    /**
     * Boletos de hoy por estado (sin la web) y, para los que se anularon, se
     * reasignaron o se cancelaron, los usuarios que más boletos tuvieron en
     * cada uno. Un boleto cuenta a quien vendió la venta.
     *
     * @return array{total: int, estados: array<string, int>, porEstado: array<string, list<array{id: int, nombre: string, usuario: string, boletos: int}>>}
     */
    public function boletosDeHoy(): array
    {
        $estados = array_fill_keys(self::ESTADOS_BOLETO, 0);
        $porEstado = [];
        foreach ($this->boletosHoy as $f) {
            $estados[$f["estado"]] = ($estados[$f["estado"]] ?? 0) + $f["boletos"];
            if ($f["usuarioId"] === null || !in_array($f["estado"], self::ESTADOS_RANKING, true)) {
                continue;
            }
            $u = &$porEstado[$f["estado"]][$f["usuarioId"]];
            $u ??= ["id" => $f["usuarioId"], "nombre" => (string) $f["nombre"], "usuario" => (string) $f["usuario"], "boletos" => 0];
            $u["boletos"] += $f["boletos"];
            unset($u);
        }

        $ranking = [];
        foreach (self::ESTADOS_RANKING as $estado) {
            $lista = array_values($porEstado[$estado] ?? []);
            usort($lista, static fn(array $a, array $b) => [$b["boletos"], $a["nombre"]] <=> [$a["boletos"], $b["nombre"]]);
            $ranking[$estado] = array_slice($lista, 0, self::USUARIOS_TOP);
        }

        return [
            "total" => array_sum($estados),
            "estados" => $estados,
            "porEstado" => $ranking,
        ];
    }

    /**
     * Rendimiento por ruta de las salidas que ya salieron en el periodo, por
     * ingresos. `senal`: `saturada` (pide frecuencia o bus más grande) o
     * `baja` (sobran salidas o hay que promoverla), con al menos
     * `MIN_SALIDAS_RUTA` salidas para no juzgar por una sola.
     *
     * @return list<array<string, mixed>>
     */
    public function rutas(): array
    {
        $grupos = [];
        foreach ([true, false] as $actual) {
            foreach ($this->salidasSalieron($actual) as $s) {
                if ($s->cancelada()) {
                    continue;
                }
                $grupos[$s->rutaId][$actual ? "actual" : "anterior"][] = $s;
                $grupos[$s->rutaId]["nombre"] = $s->ruta;
            }
        }

        $filas = [];
        foreach ($grupos as $id => $g) {
            $actual = $g["actual"] ?? [];
            if ($actual === []) {
                continue;
            }
            $ingresos = array_sum(array_map(static fn(SalidaTablero $s) => $s->ingresos, $actual));
            $ocupacion = self::ocupacionDe($actual);
            $n = count($actual);
            $filas[] = [
                "id" => $id,
                "nombre" => $g["nombre"],
                "salidas" => $n,
                "boletos" => array_sum(array_map(static fn(SalidaTablero $s) => $s->boletos, $actual)),
                "ingresos" => $ingresos,
                "ingresoPorSalida" => intdiv($ingresos, $n),
                "ocupacion" => $ocupacion,
                "ocupacionAnterior" => self::ocupacionDe($g["anterior"] ?? []),
                "senal" => self::senalRuta($ocupacion, $n),
            ];
        }
        usort($filas, static fn(array $a, array $b) => [$b["ingresos"], $a["nombre"]] <=> [$a["ingresos"], $b["nombre"]]);

        return $filas;
    }

    public static function senalRuta(?float $ocupacion, int $salidas): ?string
    {
        if ($ocupacion === null || $salidas < self::MIN_SALIDAS_RUTA) {
            return null;
        }

        return match (true) {
            $ocupacion >= self::SATURADA => "saturada",
            $ocupacion < self::BAJA => "baja",
            default => null,
        };
    }

    /**
     * Ocupación media por día de la semana (1 = lunes) y hora de salida, de
     * las salidas que ya salieron en las últimas semanas: dónde falta y dónde
     * sobra oferta.
     *
     * @return array{desde: string, celdas: list<array{dia: int, hora: int, salidas: int, ocupacion: ?float, boletos: int}>}
     */
    public function demanda(): array
    {
        $p = $this->periodo;
        $inicio = $p->inicioDemanda();
        $celdas = [];
        foreach ($this->salidas as $s) {
            if ($s->cancelada() || $s->fecha < $inicio || $s->fecha > $p->ahora) {
                continue;
            }
            $llave = $s->fecha->format("N-G");
            $celdas[$llave][] = $s;
        }
        $r = [];
        foreach ($celdas as $llave => $lista) {
            [$dia, $hora] = array_map("intval", explode("-", $llave));
            $r[] = [
                "dia" => $dia,
                "hora" => $hora,
                "salidas" => count($lista),
                "ocupacion" => self::ocupacionDe($lista),
                "boletos" => array_sum(array_map(static fn(SalidaTablero $s) => $s->boletos, $lista)),
            ];
        }
        usort($r, static fn(array $a, array $b) => [$a["dia"], $a["hora"]] <=> [$b["dia"], $b["hora"]]);

        return ["desde" => $inicio->format("Y-m-d"), "celdas" => $r];
    }

    /**
     * Lo ya vendido de hoy y los próximos 7 días, contra cómo terminó el
     * mismo día de la semana anterior: si el ritmo de venta va adelantado o
     * atrasado.
     *
     * @return list<array{dia: string, salidas: int, asientos: int, boletos: int, ocupacion: ?float, semanaAnterior: ?float}>
     */
    public function anticipada(): array
    {
        $porDia = [];
        foreach ($this->salidas as $s) {
            if (!$s->cancelada()) {
                $porDia[$s->fecha->format("Y-m-d")][] = $s;
            }
        }
        $r = [];
        $hoy = $this->periodo->hoy();
        for ($i = 0; $i < 8; $i++) {
            $dia = $hoy->modify("+{$i} days");
            $lista = $porDia[$dia->format("Y-m-d")] ?? [];
            $r[] = [
                "dia" => $dia->format("Y-m-d"),
                "salidas" => count($lista),
                "asientos" => array_sum(array_map(static fn(SalidaTablero $s) => $s->asientos, $lista)),
                "boletos" => array_sum(array_map(static fn(SalidaTablero $s) => $s->boletos, $lista)),
                "ocupacion" => self::ocupacionDe($lista),
                "semanaAnterior" => self::ocupacionDe($porDia[$dia->modify("-7 days")->format("Y-m-d")] ?? []),
            ];
        }

        return $r;
    }

    /**
     * Puntos de venta por lo que ingresaron, los primeros `$tope` (null: todos).
     *
     * @return list<array{tipo: string, nombre: string, ingresos: int, boletos: int, anterior: int}>
     */
    public function puntos(?int $tope = 10): array
    {
        $r = $this->puntos;
        usort($r, static fn(array $a, array $b) => $b["ingresos"] <=> $a["ingresos"]);

        return array_slice($r, 0, $tope);
    }

    /**
     * Agencias activas con lo que su saldo alcanza a cubrir a su ritmo de
     * venta de la última semana, las más justas primero.
     *
     * @return list<array{id: int, nombre: string, saldo: int, moneda: string, ventaDiaria: int, cobertura: ?float}>
     */
    public function agencias(): array
    {
        $r = array_map(static function (array $a): array {
            $diaria = intdiv($a["ventas7"], 7);

            return [
                "id" => $a["id"],
                "nombre" => $a["nombre"],
                "saldo" => $a["saldo"],
                "moneda" => $a["moneda"],
                "ventaDiaria" => $diaria,
                "cobertura" => $diaria > 0 ? round(max(0, $a["saldo"]) / $diaria, 1) : null,
            ];
        }, array_values(array_filter($this->agencias, static fn(array $a) => $a["ventas7"] > 0 || $a["saldo"] > 0)));
        usort($r, static fn(array $a, array $b) => [$a["cobertura"] ?? INF, $a["nombre"]] <=> [$b["cobertura"] ?? INF, $b["nombre"]]);

        return $r;
    }

    /** @return array<string, mixed> */
    public function flotaResumen(): array
    {
        $enServicio = [];
        foreach ($this->salidasSalieron(true) as $s) {
            if (!$s->cancelada() && $s->busId !== null) {
                $enServicio[$s->busId] = true;
            }
        }

        return [
            "buses" => $this->flota["buses"],
            "enServicio" => count($enServicio),
            "vencidas" => $this->flota["vencidas"],
            "porVencer" => $this->flota["porVencer"],
        ];
    }

    /**
     * Lo que pide acción, de lo más grave a lo menos. `nivel`: `critico`
     * (riesgo legal o de operación), `alerta` (dinero o servicio en juego) o
     * `aviso` (oportunidad). `destino`: ruta del frontend donde se resuelve.
     *
     * @return list<array{nivel: string, codigo: string, titulo: string, detalle: string, destino: ?string}>
     */
    public function alertas(): array
    {
        $p = $this->periodo;
        $a = [];

        $detenidas = $this->programador["detenidas"];
        if ($detenidas !== []) {
            $a[] = self::alerta(
                "critico",
                "programador_detenido",
                sprintf("%s del programador sin correr", self::plural(count($detenidas), "tarea", "tareas")),
                self::lista($detenidas) . ". Sin el programador no se sincroniza, no se certifican facturas pendientes ni salen los reportes: revisar los contenedores programador y worker.",
                null,
            );
        }
        if ($this->programador["fallidos"] > 0) {
            $a[] = self::alerta(
                "alerta",
                "mensajes_fallidos",
                sprintf("%s en segundo plano agotaron sus reintentos", self::plural($this->programador["fallidos"], "trabajo", "trabajos")),
                "Correos, reportes o despliegues de esquemas que no se completaron. Revisar con messenger:failed:show y reintentar.",
                null,
            );
        }

        $f = $this->facturacion;
        if ($f["pendientes"] > 0) {
            $antigua = $f["masAntigua"] !== null ? new \DateTimeImmutable($f["masAntigua"]) : null;
            $vieja = $antigua !== null && $antigua < $p->ahora->modify("-24 hours");
            $a[] = self::alerta(
                $vieja ? "critico" : "alerta",
                "facturas_pendientes",
                sprintf("%s sin factura electrónica", self::plural($f["pendientes"], "venta", "ventas")),
                ($vieja ? sprintf("La más antigua es del %s. ", $antigua->format("d/m H:i")) : "")
                    . ($f["conError"] > 0 ? sprintf("%d con error del certificador. ", $f["conError"]) : "")
                    . "El programador las reintenta cada 10 minutos; si no bajan, revisar credenciales FEL.",
                "/reporte/detalle-factura-boletos",
            );
        }

        $vencidas = count($this->flota["vencidas"]);
        if ($vencidas > 0) {
            $a[] = self::alerta(
                "critico",
                "tarjetas_vencidas",
                sprintf("%s con tarjeta de operaciones vencida", self::plural($vencidas, "bus", "buses")),
                self::lista(array_column($this->flota["vencidas"], "codigo")) . ". No deberían circular hasta renovarla.",
                "/lista/bus",
            );
        }

        if ($this->sinCerrar !== []) {
            $n = count($this->sinCerrar);
            $a[] = self::alerta(
                "alerta",
                "salidas_sin_cerrar",
                sprintf("%s y no se %s", self::plural($n, "salida ya llegó", "salidas ya llegaron"), $n === 1 ? "finalizó" : "finalizaron"),
                "Sus boletos siguen sin cerrar (emitidos sin cancelar, tránsito sin finalizar) y la ocupación y los manifiestos quedan incompletos.",
                "/salidas/chequeo",
            );
        }

        $justas = array_values(array_filter($this->agencias(), static fn(array $ag) => $ag["cobertura"] !== null && $ag["cobertura"] < self::DIAS_COBERTURA));
        if ($justas !== []) {
            $a[] = self::alerta(
                "alerta",
                "agencias_sin_saldo",
                sprintf("%s con saldo para menos de %d días", self::plural(count($justas), "agencia", "agencias"), self::DIAS_COBERTURA),
                self::lista(array_column($justas, "nombre")) . ". Sin saldo dejan de vender.",
                "/agencias/saldos",
            );
        }

        $k = $this->kpis();
        $ing = $k["ingresos"];
        if ($ing["anterior"] > 0 && $ing["actual"] < $ing["anterior"] * 0.85) {
            $a[] = self::alerta(
                "alerta",
                "ingresos_bajan",
                sprintf("Ingresos %d%% por debajo del periodo anterior", (int) round((1 - $ing["actual"] / $ing["anterior"]) * 100)),
                sprintf("Comparado con %s. Revisar canales y rutas para ubicar la caída.", $p->rotuloAnterior()),
                null,
            );
        }

        $tasa = $k["tasaAnulacion"]["actual"];
        if ($tasa !== null && $tasa > 0.05 && $k["anulados"]["actual"] >= 10) {
            $a[] = self::alerta(
                "alerta",
                "anulaciones_altas",
                sprintf("%s%% de los boletos se anularon", self::porciento($tasa)),
                sprintf("%d boletos anulados en el periodo. Una tasa alta suele ser errores de venta o reventa fuera de sistema.", $k["anulados"]["actual"]),
                "/boletos",
            );
        }

        $porVencer = count($this->flota["porVencer"]);
        if ($porVencer > 0) {
            $a[] = self::alerta(
                "aviso",
                "tarjetas_por_vencer",
                sprintf("%s con tarjeta de operaciones por vencer en 30 días", self::plural($porVencer, "bus", "buses")),
                self::lista(array_map(static fn(array $b) => sprintf("%s (%s)", $b["codigo"], (new \DateTimeImmutable($b["vence"]))->format("d/m")), $this->flota["porVencer"])) . ".",
                "/lista/bus",
            );
        }

        if ($this->depositos["pendientes"] > 0) {
            $a[] = self::alerta(
                "aviso",
                "depositos_pendientes",
                sprintf("%s de agencia por acreditar", self::plural($this->depositos["pendientes"], "depósito", "depósitos")),
                "El saldo de la agencia no sube hasta que se acredita.",
                "/agencias/depositos",
            );
        }

        $proximas = fn(string $plazo) => array_filter(
            $this->salidas,
            static fn(SalidaTablero $s) => !$s->cancelada() && $s->fecha > $p->ahora && $s->fecha <= $p->ahora->modify($plazo) && $s->ocupacion() !== null,
        );
        $llenas = array_filter($proximas("+24 hours"), static fn(SalidaTablero $s) => $s->ocupacion() >= 0.9);
        if ($llenas !== []) {
            $a[] = self::alerta(
                "aviso",
                "salidas_llenas",
                sprintf("%s de las próximas 24 h casi llenas", self::plural(count($llenas), "salida", "salidas")),
                "90% o más vendido: valorar un bus más grande o una salida de refuerzo.",
                "/salidas",
            );
        }
        $vacias = array_filter($proximas("+6 hours"), static fn(SalidaTablero $s) => $s->ocupacion() < 0.15);
        if ($vacias !== []) {
            $a[] = self::alerta(
                "aviso",
                "salidas_vacias",
                sprintf("%s en menos de 6 h con menos del 15%% vendido", self::plural(count($vacias), "salida sale", "salidas salen")),
                "Candidatas a unir con otra salida de la misma ruta o a promoverse en la página.",
                "/salidas",
            );
        }

        return $a;
    }

    /**
     * Salidas del periodo actual o del anterior que ya salieron.
     *
     * @return list<SalidaTablero>
     */
    private function salidasSalieron(bool $actual): array
    {
        $p = $this->periodo;

        return array_values(array_filter($this->salidas, static fn(SalidaTablero $s) => $actual ? $p->esActual($s->fecha) : $p->esAnterior($s->fecha)));
    }

    /**
     * Ocupación conjunta (asiento-tramos vendidos / ofrecidos) de las salidas
     * con capacidad conocida: pesa más una salida grande que una chica.
     *
     * @param list<SalidaTablero> $salidas
     */
    public static function ocupacionDe(array $salidas): ?float
    {
        $ocupado = 0;
        $capacidad = 0;
        foreach ($salidas as $s) {
            if ($s->asientos > 0 && !$s->cancelada()) {
                $ocupado += $s->ocupado;
                $capacidad += $s->capacidad();
            }
        }

        return $capacidad > 0 ? round(min(1, $ocupado / $capacidad), 4) : null;
    }

    private static function redondear(?float $v): ?float
    {
        return $v === null ? null : round($v, 4);
    }

    /** @return array{nivel: string, codigo: string, titulo: string, detalle: string, destino: ?string} */
    private static function alerta(string $nivel, string $codigo, string $titulo, string $detalle, ?string $destino): array
    {
        return ["nivel" => $nivel, "codigo" => $codigo, "titulo" => $titulo, "detalle" => trim($detalle), "destino" => $destino];
    }

    private static function plural(int $n, string $uno, string $varios): string
    {
        return sprintf("%d %s", $n, $n === 1 ? $uno : $varios);
    }

    private static function porciento(float $v): string
    {
        return rtrim(rtrim(number_format($v * 100, 1, ".", ""), "0"), ".");
    }

    /** @param list<string> $nombres */
    private static function lista(array $nombres, int $max = 5): string
    {
        $resto = count($nombres) - $max;

        return implode(", ", array_slice($nombres, 0, $max)) . ($resto > 0 ? sprintf(" y %d más", $resto) : "");
    }
}
