<?php

declare(strict_types=1);

namespace App\Venta;

/**
 * Estado de cada asiento de un salida para un tramo, a partir de lo que
 * lo ocupa (pura). Un asiento está ocupado para un tramo si algún ocupante
 * tiene un tramo que se solapa. Un ocupante con un trayecto ajeno al
 * itinerario (datos inconsistentes) ocupa el salida completo: ante la
 * duda, no se vende dos veces.
 */
final class Ocupacion
{
    /** Vendido o en venta (los asientos de una venta pendiente de factura cuentan). */
    public const VENDIDO = "vendido";
    /** Apartado por otra persona en la página web (la "precompra"). */
    public const RESERVADO = "reservado";
    /** Apartado por el propio carrito (web). */
    public const PROPIO = "propio";

    /** Vendidos sin cobro (`sinCobro`): cortesía de taquilla o voucher del legado. */
    public const CORTESIA = "cortesia";
    public const VOUCHER = "voucher";

    /**
     * @param iterable<Ocupante> $ocupantes
     *
     * @return array<int, array{estado: string, canal: ?string, sinCobro: ?string}> por id de asiento; los libres no aparecen
     */
    public static function estados(
        Itinerario $itinerario,
        Tramo $tramo,
        iterable $ocupantes,
        ?string $tokenPropio = null,
    ): array {
        $prioridad = [self::PROPIO => 1, self::RESERVADO => 2, self::VENDIDO => 3];
        $estados = [];

        foreach ($ocupantes as $o) {
            $suyo = $itinerario->tramo($o->trayectoId) ?? $itinerario->completo();
            if (!$suyo->seSolapaCon($tramo)) {
                continue;
            }

            $estado = match (true) {
                $o->tipo === Ocupante::VENDIDO => self::VENDIDO,
                $tokenPropio !== null && $o->token === $tokenPropio => self::PROPIO,
                default => self::RESERVADO,
            };

            $actual = $estados[$o->asientoId]["estado"] ?? null;
            if ($actual === null || $prioridad[$estado] > $prioridad[$actual]) {
                $estados[$o->asientoId] = [
                    "estado" => $estado,
                    "canal" => $o->canal?->value,
                    "sinCobro" => $o->sinCobro,
                ];
            }
        }

        return $estados;
    }

    /**
     * Asientos de `$pedidos` que no se pueden apartar (vendidos o reservados
     * por otro). Los propios sí se pueden.
     *
     * @param list<int>                                         $pedidos
     * @param array<int, array{estado: string, canal: ?string, sinCobro: ?string}> $estados
     *
     * @return list<int>
     */
    public static function noDisponibles(array $pedidos, array $estados): array
    {
        return array_values(array_filter(
            $pedidos,
            static fn(int $id) => isset($estados[$id])
                && $estados[$id]["estado"] !== self::PROPIO,
        ));
    }
}
