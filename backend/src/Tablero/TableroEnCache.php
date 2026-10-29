<?php

declare(strict_types=1);

namespace App\Tablero;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * El tablero se sirve de una copia que la tarea `refrescar_tablero` rehace
 * cada minuto mientras alguien lo mira (ADR-044): calcularlo agrega los
 * boletos de varias semanas y tarda cientos de milisegundos; leer la copia,
 * unos pocos. Una copia de más de `FRESCA` segundos (el worker se atrasó o
 * nadie lo miraba) o de otro día se calcula en la petición, como antes.
 *
 * Una copia por periodo y empresa (null = todas). Van en `cache.tablero`
 * (PostgreSQL): el backend y el worker no comparten disco.
 */
final class TableroEnCache
{
    /** Segundos que una copia sirve sin calcular en la petición. */
    public const FRESCA = 120;
    /** Segundos sin que nadie lo mire tras los que la tarea deja de rehacerlo. */
    public const INTERES = 600;
    private const LECTURAS = "lecturas";

    public function __construct(
        private readonly ConsultaTablero $consulta,
        #[Autowire(service: "cache.tablero")]
        private readonly CacheItemPoolInterface $cache,
        private readonly ClockInterface $reloj,
    ) {}

    /** @return array<string, mixed> el tablero (`Tablero::toArray()`) */
    public function leer(int $dias, ?int $empresa): array
    {
        $ahora = \DateTimeImmutable::createFromInterface($this->reloj->now());
        $clave = self::clave($dias, $empresa);
        $items = iterator_to_array($this->cache->getItems([self::LECTURAS, $clave]));
        $this->anotarLectura($items[self::LECTURAS], $clave, $dias, $empresa, $ahora);
        $copia = $items[$clave];
        if ($copia->isHit() && self::sirve($copia->get(), $ahora)) {
            return $copia->get()["datos"];
        }

        return $this->calcular($copia, $dias, $empresa, $ahora);
    }

    /** La tarea: rehace las copias que alguien miró hace poco y olvida las demás. Devuelve cuántas rehízo. */
    public function refrescar(): int
    {
        $ahora = \DateTimeImmutable::createFromInterface($this->reloj->now());
        $item = $this->cache->getItem(self::LECTURAS);
        $lecturas = $item->isHit() && is_array($item->get()) ? $item->get() : [];
        $vigentes = self::vigentes($lecturas, $ahora);
        foreach ($vigentes as $clave => $l) {
            $this->calcular($this->cache->getItem($clave), $l["dias"], $l["empresa"], $ahora);
        }
        if (count($vigentes) !== count($lecturas)) {
            $item->set($vigentes);
            $this->cache->save($item);
            $this->cache->deleteItems(array_keys(array_diff_key($lecturas, $vigentes)));
        }

        return count($vigentes);
    }

    /** ¿La copia sirve ahora? Calculada hace menos de `FRESCA` segundos, hoy. */
    public static function sirve(mixed $copia, \DateTimeImmutable $ahora): bool
    {
        return is_array($copia)
            && ($copia["dia"] ?? null) === $ahora->format("Y-m-d")
            && ($copia["calculado"] ?? 0) >= $ahora->getTimestamp() - self::FRESCA;
    }

    /**
     * Las lecturas de menos de `INTERES` segundos: lo que alguien sigue mirando.
     *
     * @param array<string, array{dias: int, empresa: ?int, leido: int}> $lecturas
     *
     * @return array<string, array{dias: int, empresa: ?int, leido: int}>
     */
    public static function vigentes(array $lecturas, \DateTimeImmutable $ahora): array
    {
        return array_filter($lecturas, static fn(array $l) => $l["leido"] >= $ahora->getTimestamp() - self::INTERES);
    }

    public static function clave(int $dias, ?int $empresa): string
    {
        return sprintf("tablero.%d.%s", $dias, $empresa ?? "todas");
    }

    /** Anota que lo miraron; una escritura por minuto basta para saber que lo siguen mirando. */
    private function anotarLectura(CacheItemInterface $item, string $clave, int $dias, ?int $empresa, \DateTimeImmutable $ahora): void
    {
        $lecturas = $item->isHit() && is_array($item->get()) ? $item->get() : [];
        if (($lecturas[$clave]["leido"] ?? 0) > $ahora->getTimestamp() - 60) {
            return;
        }
        $lecturas[$clave] = ["dias" => $dias, "empresa" => $empresa, "leido" => $ahora->getTimestamp()];
        $this->cache->save($item->set($lecturas));
    }

    /** @return array<string, mixed> */
    private function calcular(CacheItemInterface $item, int $dias, ?int $empresa, \DateTimeImmutable $ahora): array
    {
        $datos = $this->consulta->tablero(new Periodo($dias, $ahora), $empresa)->toArray();
        // Se borra sola si la tarea deja de rehacerla.
        $this->cache->save($item->set(["calculado" => $ahora->getTimestamp(), "dia" => $ahora->format("Y-m-d"), "datos" => $datos])->expiresAfter(self::INTERES));

        return $datos;
    }
}
