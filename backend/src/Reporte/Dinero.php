<?php

declare(strict_types=1);

namespace App\Reporte;

/** Formato de importes en centavos para los reportes (separador de miles `,`, decimales `.`). */
final class Dinero
{
    public static function numero(int $centavos): string
    {
        return number_format($centavos / 100, 2, ".", ",");
    }

    public static function con(int $centavos, string $moneda): string
    {
        return sprintf("%s %s", $moneda, self::numero($centavos));
    }

    /** Para plantillas Twig: `d.num(x)` y `d.con(x, moneda)`. */
    public function num(int $centavos): string
    {
        return self::numero($centavos);
    }

    public function conMoneda(int $centavos, string $moneda): string
    {
        return self::con($centavos, $moneda);
    }
}
