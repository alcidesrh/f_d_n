<?php

declare(strict_types=1);

namespace App\Inventario;

/**
 * Cantidades y costos del inventario en enteros, sin flotantes (ADR-071).
 *
 * - Cantidad: centésimas de la unidad del artículo (`"12.5"` → 1250). Se
 *   guarda `decimal(12,2)`.
 * - Costo unitario: diezmilésimas de quetzal (`"3.5"` → 35000). Se guarda
 *   `decimal(14,4)`: el promedio ponderado necesita más que centavos.
 *
 * Puro.
 */
final class Cantidad
{
    /** Centésimas desde lo que escribe el usuario o lo que guarda la base; null si no es un número con hasta 2 decimales. */
    public static function centesimas(mixed $valor): ?int
    {
        return self::entero($valor, 2);
    }

    /** Diezmilésimas de quetzal; null si no es un número con hasta 4 decimales. */
    public static function diezmilesimas(mixed $valor): ?int
    {
        return self::entero($valor, 4);
    }

    /** 1250 → `"12.50"` */
    public static function texto(int $centesimas): string
    {
        return self::decimal($centesimas, 2);
    }

    /** 35000 → `"3.5000"` */
    public static function costoTexto(int $diezmilesimas): string
    {
        return self::decimal($diezmilesimas, 4);
    }

    /**
     * Costo promedio ponderado tras una entrada: lo que había (`$q0` a `$c0`)
     * más lo que entra (`$q` a `$c`). Si no había nada (o había de menos por
     * un ajuste), vale el costo de lo que entra.
     */
    public static function promedio(int $q0, int $c0, int $q, int $c): int
    {
        if ($q <= 0) {
            return $c0;
        }
        if ($q0 <= 0) {
            return $c;
        }

        return self::dividir($q0 * $c0 + $q * $c, $q0 + $q);
    }

    /** Valor en centavos de `$q` centésimas a `$c` diezmilésimas: q·c / 10⁴, redondeado. */
    public static function valorCentavos(int $q, int $c): int
    {
        return self::dividir($q * $c, 10_000);
    }

    /** División entera redondeada a la mitad hacia afuera. */
    private static function dividir(int $a, int $b): int
    {
        $q = intdiv($a, $b);
        $r = $a % $b;
        if (2 * abs($r) >= abs($b)) {
            $q += ($a < 0) === ($b < 0) ? 1 : -1;
        }

        return $q;
    }

    private static function entero(mixed $valor, int $decimales): ?int
    {
        if (is_int($valor)) {
            return $valor * 10 ** $decimales;
        }
        if (is_float($valor)) {
            $valor = rtrim(rtrim(number_format($valor, $decimales + 2, ".", ""), "0"), ".");
        }
        if (!is_string($valor)) {
            return null;
        }
        $valor = str_replace(",", "", trim($valor));
        if (!preg_match('/^(-?)(\d{1,12})(?:\.(\d{0,' . $decimales . '}))?$/', $valor, $m)) {
            return null;
        }
        $fraccion = str_pad($m[3] ?? "", $decimales, "0");
        $n = (int) $m[2] * 10 ** $decimales + (int) $fraccion;

        return $m[1] === "-" ? -$n : $n;
    }

    private static function decimal(int $n, int $decimales): string
    {
        $base = 10 ** $decimales;
        $signo = $n < 0 ? "-" : "";
        $n = abs($n);

        return sprintf("%s%d.%0{$decimales}d", $signo, intdiv($n, $base), $n % $base);
    }
}
