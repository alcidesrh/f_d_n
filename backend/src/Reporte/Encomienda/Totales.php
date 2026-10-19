<?php

declare(strict_types=1);

namespace App\Reporte\Encomienda;

/** Sumas comunes de los reportes de encomiendas (centavos). */
final class Totales
{
    /**
     * @param list<LineaEncomienda> $lineas
     *
     * @return array{cantidad: int, bultos: int, total: int, pagado: int, porCobrar: int}
     */
    public static function de(array $lineas): array
    {
        $t = ["cantidad" => 0, "bultos" => 0, "total" => 0, "pagado" => 0, "porCobrar" => 0];
        foreach ($lineas as $l) {
            ++$t["cantidad"];
            $t["bultos"] += $l->bultos;
            if (!$l->anulada()) {
                $t["total"] += $l->total;
            }
            $t["pagado"] += $l->pagado();
            $t["porCobrar"] += $l->porCobrar();
        }

        return $t;
    }

    /**
     * Agrupa conservando el orden de aparición.
     *
     * @template T
     *
     * @param list<T> $lineas
     * @param callable(T): string $clave
     *
     * @return array<string, list<T>>
     */
    public static function agrupar(array $lineas, callable $clave): array
    {
        $grupos = [];
        foreach ($lineas as $l) {
            $grupos[$clave($l)][] = $l;
        }

        return $grupos;
    }
}
