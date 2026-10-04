<?php

declare(strict_types=1);

namespace App\Reporte;

/** Lectura validada de los parámetros de query de los reportes. */
final class Parametros
{
    public static function dia(mixed $v, string $campo): \DateTimeImmutable
    {
        $d = is_string($v) ? \DateTimeImmutable::createFromFormat("!Y-m-d", $v) : false;
        if ($d === false || $d->format("Y-m-d") !== $v) {
            throw new ReporteRechazado(sprintf("Indica %s con el formato AAAA-MM-DD.", $campo), "fecha_invalida", 400);
        }

        return $d;
    }

    public static function entero(mixed $v, string $campo): ?int
    {
        if ($v === null || $v === "") {
            return null;
        }
        if (!is_scalar($v) || !ctype_digit((string) $v)) {
            throw new ReporteRechazado(sprintf("%s no es válido.", ucfirst($campo)), "parametro_invalido", 400);
        }

        return (int) $v;
    }

    public static function texto(mixed $v, int $max = 100): ?string
    {
        $t = is_string($v) ? trim($v) : "";

        return $t === "" ? null : mb_substr($t, 0, $max);
    }

    public static function bandera(mixed $v): bool
    {
        return in_array($v, ["1", "true", 1, true], true);
    }
}
