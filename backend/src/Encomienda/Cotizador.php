<?php

declare(strict_types=1);

namespace App\Encomienda;

use Money\Money;

/**
 * Cálculo del precio de una encomienda, como el legado (`MultiActionController`
 * + `UtilService::aplicarDescuento`), sin base de datos (ADR-035):
 *
 * - importe de un artículo = cantidad × (tarifa + recargo por distancia);
 * - descuento por bultos de toda la encomienda: 3–5 → 5 %, 6–9 → 7 %,
 *   10 o más → 10 %, redondeado a quetzal entero;
 * - total = subtotal − descuento.
 */
final class Cotizador
{
    /** Bultos máximos por artículo (el legado: 100 unidades por encomienda). */
    public const MAX_CANTIDAD = 100;

    /**
     * @param list<array{tarifa: Money, recargo: Money, cantidad: int}> $articulos
     */
    public static function cotizar(array $articulos, string $moneda = "GTQ"): CotizacionEncomienda
    {
        $cero = Money::{$moneda}(0);
        $lineas = [];
        $subtotal = $cero;
        foreach ($articulos as $a) {
            $importe = $a["tarifa"]->add($a["recargo"])->multiply((string) $a["cantidad"]);
            $lineas[] = [...$a, "importe" => $importe];
            $subtotal = $subtotal->add($importe);
        }
        $porcentaje = self::porcentajeDescuento(array_sum(array_column($articulos, "cantidad")));
        // A quetzal entero, redondeando la mitad hacia arriba (`round(0.05 * importe, 0)` del legado).
        $descuento = Money::{$moneda}((int) round((int) $subtotal->getAmount() * $porcentaje / 100 / 100, 0, PHP_ROUND_HALF_UP) * 100);

        return new CotizacionEncomienda($lineas, $subtotal, $porcentaje, $descuento, $subtotal->subtract($descuento));
    }

    public static function porcentajeDescuento(int $bultos): int
    {
        return match (true) {
            $bultos < 3 => 0,
            $bultos <= 5 => 5,
            $bultos <= 9 => 7,
            default => 10,
        };
    }
}
