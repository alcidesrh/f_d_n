<?php

declare(strict_types=1);

namespace App\Venta;

/**
 * Asientos que conviene vender para un tramo (puro, ADR-030): los libres
 * cuyo hueco libre alrededor del tramo es el más ajustado. Si alguien pide
 * B→C, mejor el asiento ocupado A→B y C→D que uno libre todo el viaje: así
 * quedan asientos enteros para quien viaje lejos y el bus no se fragmenta.
 * Si ningún asiento libre tiene vecinos ocupados, no hay nada que recomendar.
 */
final class Recomendacion
{
    /**
     * @param list<int>          $asientos   todos los asientos del bus
     * @param int                $ultima     posición de la última parada
     * @param iterable<Ocupante> $ocupantes  boletos vivos y reservas vigentes de la salida
     *
     * @return list<int> ids de asiento, de menor id a mayor
     */
    public static function asientos(array $asientos, int $ultima, Tramo $tramo, iterable $ocupantes): array
    {
        $tramos = [];
        foreach ($ocupantes as $o) {
            $tramos[$o->asientoId][] = new Tramo($o->sube, $o->baja);
        }
        $holguras = [];
        foreach ($asientos as $id) {
            $desde = 0;
            $hasta = $ultima;
            foreach ($tramos[$id] ?? [] as $t) {
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

        return $mejores;
    }
}
