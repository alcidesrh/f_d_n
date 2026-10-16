<?php

declare(strict_types=1);

namespace App\Venta;

/**
 * Asientos que conviene vender para un tramo (puro, ADR-030): los libres
 * cuyo hueco libre alrededor del tramo es el más ajustado. Si alguien pide
 * B→C, mejor el asiento ocupado A→B y C→D que uno libre todo el viaje: así
 * quedan asientos enteros para quien viaje lejos y el bus no se fragmenta.
 * Si ningún asiento libre tiene vecinos ocupados, no hay nada que recomendar.
 *
 * Cada recomendado lleva el ocupante que lo recomienda: el de un tramo anterior
 * más cercano al pedido o, si no hay, el de un tramo posterior más cercano. La
 * pantalla de venta pinta el asiento con el estado de ese ocupante.
 */
final class Recomendacion
{
    /**
     * @param list<int>          $asientos   todos los asientos del bus
     * @param int                $ultima     posición de la última parada
     * @param iterable<Ocupante> $ocupantes  boletos vivos y reservas vigentes de la salida
     *
     * @return array<int, Ocupante> asiento recomendado => ocupante que lo recomienda, de menor id a mayor
     */
    public static function asientos(array $asientos, int $ultima, Tramo $tramo, iterable $ocupantes): array
    {
        $porAsiento = [];
        foreach ($ocupantes as $o) {
            $porAsiento[$o->asientoId][] = $o;
        }
        $holguras = [];
        foreach ($asientos as $id) {
            $desde = 0;
            $hasta = $ultima;
            foreach ($porAsiento[$id] ?? [] as $o) {
                $t = new Tramo($o->sube, $o->baja);
                if ($t->seSolapaCon($tramo)) {
                    continue 2;
                }
                $t->hasta <= $tramo->desde ? $desde = max($desde, $t->hasta) : $hasta = min($hasta, $t->desde);
            }
            $holguras[$id] = ($hasta - $desde) - ($tramo->hasta - $tramo->desde);
        }
        if ($holguras === []) {
            return [];
        }
        $minima = min($holguras);
        if ($minima === $ultima - ($tramo->hasta - $tramo->desde)) {
            return [];
        }
        $mejores = array_keys(array_filter($holguras, static fn(int $h) => $h === $minima));
        sort($mejores);

        $recomendados = [];
        foreach ($mejores as $id) {
            $vecino = self::vecino($porAsiento[$id] ?? [], $tramo);
            if ($vecino !== null) {
                $recomendados[$id] = $vecino;
            }
        }

        return $recomendados;
    }

    /**
     * El ocupante que recomienda el asiento: el tramo anterior que termina más
     * cerca del pedido; si no hay ninguno, el posterior que empieza más cerca.
     *
     * @param list<Ocupante> $ocupantes del asiento, sin solapes con el tramo
     */
    private static function vecino(array $ocupantes, Tramo $tramo): ?Ocupante
    {
        $anterior = null;
        $posterior = null;
        foreach ($ocupantes as $o) {
            if ($o->baja <= $tramo->desde) {
                $anterior = $anterior === null || $o->baja > $anterior->baja ? $o : $anterior;
            } else {
                $posterior = $posterior === null || $o->sube < $posterior->sube ? $o : $posterior;
            }
        }

        return $anterior ?? $posterior;
    }
}
