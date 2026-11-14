<?php

declare(strict_types=1);

namespace App\Venta\EnLinea\Modificacion;

use Money\Money;

/**
 * Cuentas de una modificación del cliente (puras): asiento por asiento, el
 * que pasa a uno más caro paga la diferencia y el que pasa a uno más barato
 * conserva el precio pagado (la empresa no devuelve la diferencia).
 */
final class ReglasModificacion
{
    /**
     * Precio con el que queda el boleto que cambia y lo que se cobra por él.
     *
     * @return array{precio: Money, diferencia: Money, sinReembolso: Money}
     */
    public static function cambio(Money $pagado, Money $nuevo): array
    {
        $precio = Money::max($pagado, $nuevo);

        return [
            "precio" => $precio,
            "diferencia" => $precio->subtract($pagado),
            // Lo que el cliente deja de recibir por pasar a un asiento más barato.
            "sinReembolso" => $precio->subtract($nuevo),
        ];
    }

    /**
     * Total a cobrar: diferencias + asientos nuevos + penalización.
     *
     * @param list<Money> $diferencias
     * @param list<Money> $nuevos
     */
    public static function total(array $diferencias, array $nuevos, Money $penalizacion): Money
    {
        return array_reduce([...$diferencias, ...$nuevos], static fn(Money $t, Money $m) => $t->add($m), $penalizacion);
    }
}
