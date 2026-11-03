<?php

declare(strict_types=1);

namespace App\Seguimiento;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Última lectura GPS de cada bus, en `cache.gps` (PostgreSQL: la escribe una
 * petición y la lee otra, en otro proceso). Solo importa la última y se
 * olvida sola: `SeguimientoBuses` ignora de todos modos las de más de 5 min.
 */
final class GpsReciente implements FuenteGps
{
    private const VIDA_SEGUNDOS = 600;

    public function __construct(
        #[Autowire(service: 'cache.gps')]
        private readonly CacheItemPoolInterface $cache,
    ) {}

    public function guardar(string $busCodigo, Posicion $posicion): void
    {
        $item = $this->cache->getItem(self::clave($busCodigo));
        $anterior = $item->isHit() ? $item->get() : null;
        // Traccar reenvía lo que no pudo mandar: una lectura atrasada no pisa a una más nueva.
        if ($anterior instanceof Posicion && $anterior->instante > $posicion->instante) {
            return;
        }
        $this->cache->save($item->set($posicion)->expiresAfter(self::VIDA_SEGUNDOS));
    }

    public function ultima(string $busCodigo): ?Posicion
    {
        $item = $this->cache->getItem(self::clave($busCodigo));
        $valor = $item->isHit() ? $item->get() : null;

        return $valor instanceof Posicion ? $valor : null;
    }

    /** El código lo escribe quien configura el teléfono: se reduce a una clave de caché válida. */
    private static function clave(string $busCodigo): string
    {
        return 'bus_' . hash('xxh128', mb_strtoupper(trim($busCodigo)));
    }
}
