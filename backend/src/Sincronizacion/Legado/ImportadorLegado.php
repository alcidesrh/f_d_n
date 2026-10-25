<?php

declare(strict_types=1);

namespace App\Sincronizacion\Legado;

use App\Migration\Migrador;
use App\Migration\Salida\DependenciasLegado;
use App\Migration\Salida\EncomiendasLegado;
use App\Sincronizacion\Identidades;
use App\Sincronizacion\Nuevo\Traduccion;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Trae lo que cambió en el legado (ADR-042). Los triggers anotan en
 * `sync_cambio` del legado qué boleto, salida, factura o encomienda cambió;
 * aquí se vuelve a migrar su salida con el código de la migración (que no
 * duplica: busca por `legacy_id`) y se ponen al día los boletos que ya
 * estaban.
 *
 * Lo que escribió este sistema allá (`identificador_web` con `FDN:`) no se
 * trae: ya está aquí. Un solo proceso a la vez (bloqueo consultivo).
 */
class ImportadorLegado
{
    private const BLOQUEO = 4204202;

    /** Tras tantos intentos fallidos, el cambio se deja de reintentar. */
    public const MAX_INTENTOS = 20;

    public function __construct(
        private readonly Connection $db,
        private readonly ConexionLegado $legado,
        private readonly Migrador $migrador,
        private readonly DependenciasLegado $dependencias,
        private readonly EncomiendasLegado $encomiendas,
        private readonly ActualizadorBoletos $actualizador,
        private readonly Identidades $identidades,
        private readonly LoggerInterface $logger,
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
        $boletos = [];
        foreach ($this->agrupar($cambios) as [$tipo, $id, $cambio, $boleto]) {
            if ($tipo === "salida") {
                $porSalida[$id][] = $cambio;
                if ($boleto !== null) {
                    $boletos[$id][] = $boleto;
                }
            } else {
                $porEncomienda[$id][] = $cambio;
            }
        }

        $resumen = ["importados" => 0, "fallidos" => 0];
        $this->conEnganches(function () use ($porSalida, $porEncomienda, $boletos, &$resumen) {
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
     * factura o la salida misma) o una encomienda.
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
        $contadores = $this->migrador->migrarSalida(salidasPrefetchadas: $filas);
        if (($contadores["errores"] ?? 0) > 0) {
            throw new \RuntimeException("La migración de la salida {$legacyId} del legado falló (ver log).");
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
     * quitados al terminar): lo de FDN no se trae y los clientes creados
     * aquí se reconocen.
     */
    private function conEnganches(\Closure $trabajo): void
    {
        $deFdn = static fn(array $fila): bool => str_starts_with((string) ($fila["identificador_web"] ?? ""), Traduccion::PREFIJO);
        $this->migrador->excluirBoletos($deFdn);
        $this->encomiendas->excluir($deFdn);
        $this->dependencias->equivalencias(function (string $tabla, int $id): ?int {
            if ($tabla !== "cliente") {
                return null;
            }
            $nuevo = $this->db->fetchOne("SELECT id_nuevo FROM sync_vinculo WHERE entidad = 'cliente' AND id_legado = :id", ["id" => $id]);

            return $nuevo === false ? null : (int) $nuevo;
        });
        try {
            $trabajo();
        } finally {
            $this->migrador->excluirBoletos(null);
            $this->encomiendas->excluir(null);
            $this->dependencias->equivalencias(null);
            // La migración deja las identidades en el mayor id + 1.
            $this->identidades->apartarRango();
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
            $this->legado->ejecutar(
                "UPDATE sync_cambio SET intentos = intentos + 1, error = :error WHERE id IN (" . implode(",", array_map("intval", $cambios)) . ")",
                ["error" => mb_substr($e->getMessage(), 0, 2000)],
            );
            $resumen["fallidos"]++;
        }
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
