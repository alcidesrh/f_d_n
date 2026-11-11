<?php

declare(strict_types=1);

namespace App\Sincronizacion\Legado;

use App\Sincronizacion\Legado\Importacion\Migrador;
use App\Sincronizacion\Legado\Importacion\PdoLegado;
use App\Sincronizacion\Legado\Importacion\DependenciasLegado;
use App\Sincronizacion\Legado\Importacion\EncomiendasLegado;
use App\Sincronizacion\Nuevo\ClientesLocales;
use App\Sincronizacion\Nuevo\Traduccion;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Attribute\Target;

/**
 * Trae lo que cambió en el legado (ADR-042). Los triggers anotan en
 * `sync_cambio` del legado qué boleto, salida, factura o encomienda cambió;
 * aquí se vuelve a migrar su salida con el código de la migración (que no
 * duplica: busca por `legacy_id`) y se ponen al día los boletos que ya
 * estaban. Los clientes nuevos del legado se crean aquí con su id.
 *
 * Lo que escribió este sistema allá (`identificador_web` con `FDN:`) no se
 * trae: ya está aquí. Un solo proceso a la vez (bloqueo consultivo).
 */
class ImportadorLegado
{
    private const BLOQUEO = 4204202;

    /** Tras tantos intentos fallidos, el cambio se deja de reintentar. */
    public const MAX_INTENTOS = 20;

    /** Salidas que intenta cada barrido: retiene el cerrojo del ciclo mientras corre. */
    public const MAX_BARRIDO = 50;

    /** Una salida ya pasada se sigue buscando estos días si tiene boletos. */
    private const DIAS_BARRIDO = 2;

    /** `salida.estado_id` cancelada en el legado. */
    private const SALIDA_CANCELADA = 4;

    public function __construct(
        private readonly Connection $db,
        private readonly ConexionLegado $legado,
        private readonly Migrador $migrador,
        private readonly DependenciasLegado $dependencias,
        private readonly EncomiendasLegado $encomiendas,
        private readonly ActualizadorBoletos $actualizador,
        private readonly ClientesLocales $locales,
        private readonly LoggerInterface $logger,
        #[Target("oldPdo")] private readonly \PDO $lectura,
    ) {}

    /**
     * Procesa hasta `$limite` cambios pendientes del legado.
     *
     * @return array<string, int> contadores
     */
    public function procesar(int $limite = 500): array
    {
        $resumen = ["importados" => 0, "fallidos" => 0];
        if (!$this->db->fetchOne("SELECT pg_try_advisory_lock(:k)", ["k" => self::BLOQUEO])) {
            return $resumen;
        }
        try {
            $cambios = $this->legado->filas(
                "SELECT TOP " . max(1, $limite) . " id, tabla, registro_id FROM sync_cambio
                  WHERE procesado IS NULL AND intentos < :max ORDER BY id",
                ["max" => self::MAX_INTENTOS],
            );
            if ($cambios === []) {
                return $resumen;
            }
            $resumen = $this->importar($cambios) + $resumen;
        } finally {
            $this->db->executeStatement("SELECT pg_advisory_unlock(:k)", ["k" => self::BLOQUEO]);
        }

        return $resumen;
    }

    /**
     * Busca las salidas del legado que deberían estar aquí y no están, e
     * intenta traerlas. Los triggers solo avisan de lo que cambia: una salida
     * que no cambió nunca llega, y una omitida (sin bus que inferir) no se
     * reintenta porque su cambio ya quedó procesado.
     *
     * Primero las que tienen boletos, y de esas las más próximas.
     *
     * @return array{faltan: int, traidas: int, fallidas: int, conBoletos: list<array{id: int, fecha: string, boletos: int, motivo: ?string}>}|null
     *         null si el ciclo tiene el cerrojo: el barrido se deja para la próxima
     */
    public function barrerSalidas(int $limite = self::MAX_BARRIDO): ?array
    {
        if (!$this->db->fetchOne("SELECT pg_try_advisory_lock(:k)", ["k" => self::BLOQUEO])) {
            return null;
        }
        try {
            $ocupan = implode(",", Traduccion::OCUPAN_LEGADO);
            $candidatas = $this->legado->filas(
                "SELECT x.id, x.fecha, x.boletos FROM (
                    SELECT s.id, s.fecha AS momento, CONVERT(varchar(19), s.fecha, 120) AS fecha,
                           (SELECT COUNT(*) FROM boleto b WHERE b.salida_id = s.id AND b.estado_id IN ({$ocupan})) AS boletos
                      FROM salida s
                     WHERE s.fecha >= DATEADD(day, -" . self::DIAS_BARRIDO . ", GETDATE())
                       AND COALESCE(s.estado_id, 0) <> " . self::SALIDA_CANCELADA . "
                 ) x
                 WHERE x.momento >= GETDATE() OR x.boletos > 0",
            );
            $faltan = self::faltantes($candidatas, $this->conocidas($candidatas));
            $motivos = [];
            $fallidas = 0;
            $this->conEnganches(function () use ($faltan, $limite, &$motivos, &$fallidas) {
                foreach (array_slice($faltan, 0, max(1, $limite)) as $f) {
                    $id = (int) $f["id"];
                    try {
                        $motivos[$id] = self::motivoOmision($this->salida($id));
                    } catch (\Throwable $e) {
                        // Una que falla no detiene a las demás.
                        $this->logger->warning("Sincronización: el barrido no trajo una salida del legado: {error}", ["error" => $e->getMessage(), "exception" => $e]);
                        $this->soltarConexiones();
                        $motivos[$id] = "error";
                        $fallidas++;
                    }
                }
            });
            $quedan = self::faltantes($faltan, $this->conocidas($faltan));
        } finally {
            $this->db->executeStatement("SELECT pg_advisory_unlock(:k)", ["k" => self::BLOQUEO]);
        }

        $conBoletos = [];
        foreach ($quedan as $f) {
            if ((int) $f["boletos"] > 0) {
                $id = (int) $f["id"];
                $conBoletos[] = ["id" => $id, "fecha" => (string) $f["fecha"], "boletos" => (int) $f["boletos"], "motivo" => $motivos[$id] ?? null];
            }
        }

        return ["faltan" => count($quedan), "traidas" => count($faltan) - count($quedan), "fallidas" => $fallidas, "conBoletos" => $conBoletos];
    }

    /**
     * Las candidatas que no están aquí, en el orden en que conviene traerlas.
     *
     * @param list<array<string, mixed>> $candidatas filas con `id`, `fecha` y `boletos`
     * @param list<int|string>           $conocidas  `legacy_id` de las que ya están
     *
     * @return list<array<string, mixed>>
     */
    public static function faltantes(array $candidatas, array $conocidas): array
    {
        $aqui = array_flip(array_map("strval", $conocidas));
        $faltan = array_values(array_filter($candidatas, static fn(array $c) => !isset($aqui[(string) $c["id"]])));
        usort($faltan, static fn(array $a, array $b) => [(int) $b["boletos"] > 0, $a["fecha"]] <=> [(int) $a["boletos"] > 0, $b["fecha"]]);

        return $faltan;
    }

    /**
     * Por qué la migración dejó fuera la salida, si la dejó.
     *
     * @param array<string, int> $contadores
     */
    public static function motivoOmision(array $contadores): ?string
    {
        foreach (array_keys($contadores) as $clave) {
            if (str_starts_with($clave, "omitido_salida_")) {
                return substr($clave, strlen("omitido_"));
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $filas salidas del legado
     *
     * @return list<string> los `legacy_id` de las que ya están aquí
     */
    private function conocidas(array $filas): array
    {
        if ($filas === []) {
            return [];
        }

        return $this->db->fetchFirstColumn(
            "SELECT legacy_id FROM salida WHERE legacy_id IN (:ids)",
            ["ids" => array_map(static fn(array $f) => (string) $f["id"], $filas)],
            ["ids" => ArrayParameterType::STRING],
        );
    }

    /**
     * Trae ya las salidas indicadas (las de un boleto que el legado acaba de
     * vender, al confirmar su apartado), sin esperar al ciclo.
     *
     * @param list<int> $salidas ids del legado
     *
     * @return array<string, int>
     */
    public function salidas(array $salidas): array
    {
        $contadores = [];
        $this->conEnganches(function () use ($salidas, &$contadores) {
            foreach (array_unique($salidas) as $s) {
                $this->sumar($contadores, $this->salida((int) $s));
            }
        });

        return $contadores;
    }

    /**
     * Trae ya las encomiendas indicadas (la conciliación).
     *
     * @param list<int> $encomiendas ids del legado
     *
     * @return array<string, int>
     */
    public function encomiendas(array $encomiendas): array
    {
        $contadores = [];
        $this->conEnganches(function () use ($encomiendas, &$contadores) {
            foreach (array_unique($encomiendas) as $e) {
                $this->sumar($contadores, $this->encomienda((int) $e));
            }
        });

        return $contadores;
    }

    /**
     * @param list<array{id: int|string, tabla: string, registro_id: int|string}> $cambios
     *
     * @return array<string, int>
     */
    private function importar(array $cambios): array
    {
        // Qué hay que traer, y qué cambios del legado lo pidieron.
        $porSalida = [];
        $porEncomienda = [];
        $porCliente = [];
        $boletos = [];
        foreach ($this->agrupar($cambios) as [$tipo, $id, $cambio, $boleto]) {
            if ($tipo === "salida") {
                $porSalida[$id][] = $cambio;
                if ($boleto !== null) {
                    $boletos[$id][] = $boleto;
                }
            } elseif ($tipo === "cliente") {
                $porCliente[$id][] = $cambio;
            } else {
                $porEncomienda[$id][] = $cambio;
            }
        }

        $resumen = ["importados" => 0, "fallidos" => 0];
        $this->conEnganches(function () use ($porSalida, $porEncomienda, $porCliente, $boletos, &$resumen) {
            foreach ($porCliente as $cliente => $ids) {
                $this->intentar($ids, fn() => $this->dependencias->cliente($cliente), $resumen);
            }
            foreach ($porSalida as $salida => $ids) {
                $this->intentar($ids, function () use ($salida, $boletos, &$resumen) {
                    $this->sumar($resumen, $this->salida($salida));
                    $this->sumar($resumen, $this->actualizador->actualizar(array_values(array_unique($boletos[$salida] ?? []))));
                }, $resumen);
            }
            foreach ($porEncomienda as $encomienda => $ids) {
                $this->intentar($ids, function () use ($encomienda, &$resumen) {
                    $this->sumar($resumen, $this->encomienda($encomienda));
                }, $resumen);
            }
        });
        $this->limpiar();

        return $resumen;
    }

    /**
     * Cada cambio, como lo que hay que traer: una salida (por un boleto, una
     * factura o la salida misma), una encomienda o un cliente.
     *
     * @param list<array<string, mixed>> $cambios
     *
     * @return list<array{0: string, 1: int, 2: int, 3: ?int}> tipo, id del legado, id del cambio, boleto
     */
    private function agrupar(array $cambios): array
    {
        $por = [];
        foreach ($cambios as $c) {
            $por[(string) $c["tabla"]][(int) $c["registro_id"]][] = (int) $c["id"];
        }
        $salidaDeBoleto = $this->mapa("SELECT id, salida_id AS valor FROM boleto WHERE id IN (%s)", array_keys($por["boleto"] ?? []));

        $resultado = [];
        foreach ($por["salida"] ?? [] as $salida => $ids) {
            foreach ($ids as $cambio) {
                $resultado[] = ["salida", $salida, $cambio, null];
            }
        }
        foreach ($por["boleto"] ?? [] as $boleto => $ids) {
            if (!isset($salidaDeBoleto[$boleto])) {
                // Un boleto que ya no existe en el legado no tiene nada que traer.
                $this->marcar($ids);
                continue;
            }
            foreach ($ids as $cambio) {
                $resultado[] = ["salida", $salidaDeBoleto[$boleto], $cambio, $boleto];
            }
        }
        foreach ($por["encomienda"] ?? [] as $encomienda => $ids) {
            foreach ($ids as $cambio) {
                $resultado[] = ["encomienda", $encomienda, $cambio, null];
            }
        }
        $clientes = $this->mapa("SELECT id, id AS valor FROM cliente WHERE id IN (%s)", array_keys($por["cliente"] ?? []));
        foreach ($por["cliente"] ?? [] as $cliente => $ids) {
            if (!isset($clientes[$cliente])) {
                // Un cliente que ya no existe en el legado no tiene nada que traer.
                $this->marcar($ids);
                continue;
            }
            foreach ($ids as $cambio) {
                $resultado[] = ["cliente", $cliente, $cambio, null];
            }
        }
        // Una factura: los boletos y encomiendas que factura.
        foreach ($por["factura_generada"] ?? [] as $factura => $ids) {
            $boletos = $this->legado->filas("SELECT id, salida_id FROM boleto WHERE factura_generada_id = :f", ["f" => $factura]);
            $encomiendas = $this->legado->filas("SELECT id FROM encomienda WHERE factura_generada_id = :f", ["f" => $factura]);
            foreach ($ids as $cambio) {
                foreach ($boletos as $b) {
                    $resultado[] = ["salida", (int) $b["salida_id"], $cambio, (int) $b["id"]];
                }
                foreach ($encomiendas as $e) {
                    $resultado[] = ["encomienda", (int) $e["id"], $cambio, null];
                }
                if ($boletos === [] && $encomiendas === []) {
                    $this->marcar([$cambio]);
                }
            }
        }

        return $resultado;
    }

    /**
     * Migra una salida del legado: la crea o la completa, con sus boletos y
     * encomiendas. La migración revierte la salida entera si algo falla.
     *
     * @return array<string, int>
     */
    private function salida(int $legacyId): array
    {
        $filas = $this->migrador->fetchSalidasPorIds([$legacyId]);
        if ($filas === []) {
            return [];
        }
        // La migración escribe el error de la salida en su salida de consola: se guarda para decirlo.
        $consola = new BufferedOutput();
        $contadores = $this->migrador->migrarSalidas($filas, $consola);
        if (($contadores["errores"] ?? 0) > 0) {
            $error = preg_match("/Error salida \\d+: (.+)/", $consola->fetch(), $m) ? $m[1] : "sin detalle";

            throw new \RuntimeException("La migración de la salida {$legacyId} del legado falló: {$error}");
        }

        return array_filter($contadores, static fn(int $n) => $n !== 0);
    }

    /**
     * Una encomienda del legado, con la salida en que viaja si aún no está.
     *
     * @return array<string, int>
     */
    private function encomienda(int $legacyId): array
    {
        $contadores = [];
        $salida = $this->legado->valor("SELECT primera_salida_id FROM encomienda WHERE id = :id", ["id" => $legacyId]);
        if ($salida !== null && $salida !== false && $this->db->fetchOne("SELECT 1 FROM salida WHERE legacy_id = :l", ["l" => (string) $salida]) === false) {
            // Su salida trae también la encomienda.
            return $this->salida((int) $salida);
        }
        $marca = $this->dependencias->marcar();
        $this->db->beginTransaction();
        try {
            $this->encomiendas->migrarPorIds([$legacyId], $contadores, static function (string $motivo, string $ejemplo) use (&$contadores) {
                $contadores["omitido_{$motivo}"] = ($contadores["omitido_{$motivo}"] ?? 0) + 1;
            });
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->dependencias->restaurar($marca);
            $this->encomiendas->olvidar();

            throw $e;
        }

        return array_filter($contadores, static fn(int $n) => $n !== 0);
    }

    /**
     * Con los enganches de la sincronización puestos en la migración (y
     * quitados al terminar): lo de FDN no se trae. Nada se trae mientras haya
     * clientes sin llevar al legado: el id de uno de ellos puede ser allá el
     * de otra persona, y la migración los confundiría.
     */
    private function conEnganches(\Closure $trabajo): void
    {
        if (!$this->legado->soloTraer && ($pendientes = $this->locales->pendientes()) > 0) {
            throw new \RuntimeException("Hay {$pendientes} clientes de aquí sin llevar al legado: no se trae nada hasta que pasen.");
        }
        $deFdn = static fn(array $fila): bool => str_starts_with((string) ($fila["identificador_web"] ?? ""), Traduccion::PREFIJO);
        $this->migrador->excluirBoletos($deFdn);
        $this->encomiendas->excluir($deFdn);
        try {
            $trabajo();
        } finally {
            $this->migrador->excluirBoletos(null);
            $this->encomiendas->excluir(null);
        }
    }

    /**
     * @param list<int>          $cambios ids de `sync_cambio` del legado
     * @param array<string, int> $resumen
     */
    private function intentar(array $cambios, \Closure $trabajo, array &$resumen): void
    {
        try {
            $trabajo();
            $this->marcar($cambios);
            $resumen["importados"]++;
        } catch (\Throwable $e) {
            $this->logger->warning("Sincronización: no se trajo un cambio del legado: {error}", ["error" => $e->getMessage(), "exception" => $e]);
            $this->soltarConexiones();
            $this->legado->ejecutar(
                "UPDATE sync_cambio SET intentos = intentos + 1, error = :error WHERE id IN (" . implode(",", array_map("intval", $cambios)) . ")",
                ["error" => mb_substr($e->getMessage(), 0, 2000)],
            );
            $resumen["fallidos"]++;
        }
    }

    /** Tras un error, que pudo matar la conexión («DBPROCESS is dead»): lo siguiente abre otra. */
    public function soltarConexiones(): void
    {
        if ($this->lectura instanceof PdoLegado) {
            $this->lectura->soltar();
        }
        $this->legado->cerrar();
    }

    /** @param list<int> $cambios */
    private function marcar(array $cambios): void
    {
        if ($cambios !== []) {
            $this->legado->ejecutar("UPDATE sync_cambio SET procesado = GETDATE(), error = NULL WHERE id IN (" . implode(",", array_map("intval", $cambios)) . ")");
        }
    }

    /** Lo procesado hace más de una semana sobra. */
    private function limpiar(): void
    {
        $this->legado->ejecutar("DELETE FROM sync_cambio WHERE procesado < DATEADD(day, -7, GETDATE())");
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, int> id → valor
     */
    private function mapa(string $sql, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $mapa = [];
        foreach ($this->legado->filas(sprintf($sql, implode(",", array_map("intval", $ids)))) as $f) {
            $mapa[(int) $f["id"]] = (int) $f["valor"];
        }

        return $mapa;
    }

    /**
     * @param array<string, int> $a
     * @param array<string, int> $b
     */
    private function sumar(array &$a, array $b): void
    {
        foreach ($b as $k => $n) {
            $a[$k] = ($a[$k] ?? 0) + $n;
        }
    }
}
