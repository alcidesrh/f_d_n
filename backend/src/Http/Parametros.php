<?php

declare(strict_types=1);

namespace App\Http;

use App\Excepcion\ParametroInvalido;

/**
 * Lectura validada de los parámetros de una petición (query o cuerpo), para
 * los filtros de listados y reportes: vacío es `null`; lo mal formado se
 * rechaza con 400 (`fecha_invalida`, `parametro_invalido`).
 */
final class Parametros
{
    /** `AAAA-MM-DD` (una fecha que exista), o null si viene vacío. */
    public static function dia(mixed $v, string $campo): ?\DateTimeImmutable
    {
        if ($v === null || $v === "") {
            return null;
        }
        $d = is_string($v) ? \DateTimeImmutable::createFromFormat("!Y-m-d", $v) : false;
        if ($d === false || $d->format("Y-m-d") !== $v) {
            throw new ParametroInvalido(sprintf("Indica %s con el formato AAAA-MM-DD.", $campo), "fecha_invalida");
        }

        return $d;
    }

    /** Como `dia()`, pero no puede faltar. */
    public static function diaExigido(mixed $v, string $campo): \DateTimeImmutable
    {
        return self::dia($v, $campo) ?? throw new ParametroInvalido(sprintf("Indica %s con el formato AAAA-MM-DD.", $campo), "fecha_invalida");
    }

    /** Un id o una cantidad: solo dígitos (hasta 9, cabe en un int), o null si viene vacío. */
    public static function entero(mixed $v, string $campo): ?int
    {
        if ($v === null || $v === "") {
            return null;
        }
        $t = is_scalar($v) ? trim((string) $v) : "";
        if (!ctype_digit($t) || strlen($t) > 9) {
            throw new ParametroInvalido(sprintf("%s no es válido.", ucfirst($campo)));
        }

        return (int) $t;
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
