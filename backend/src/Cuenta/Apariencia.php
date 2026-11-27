<?php

declare(strict_types=1);

namespace App\Cuenta;

/**
 * La apariencia que manda el frontend (`PUT /api/me/apariencia`), lista para
 * guardar en la cuenta. Qué ajustes hay y qué opciones admite cada uno lo
 * decide el frontend (`app/apariencia/ajustes.ts`), que además descarta al
 * leerla lo que ya no exista: aquí solo se acota la forma y el tamaño, para
 * que la columna no guarde cualquier cosa. Puro, con tests.
 */
final class Apariencia
{
    public const MAX_AJUSTES = 40;
    private const CLAVE = '/^[a-z][a-zA-Z0-9]{0,39}$/';
    private const TEXTO = '/^[a-z0-9][a-z0-9-]{0,39}$/';
    private const MAX_NUMERO = 1000;

    /**
     * Los ajustes válidos de `entrada` (clave camelCase → texto corto,
     * número o booleano); lo demás se descarta.
     *
     * @return array<string, string|int|float|bool>
     *
     * @throws \InvalidArgumentException si `entrada` no es un objeto.
     */
    public static function limpiar(mixed $entrada): array
    {
        if (!is_array($entrada) || ($entrada !== [] && array_is_list($entrada))) {
            throw new \InvalidArgumentException('La apariencia debe ser un objeto.');
        }
        $limpia = [];
        foreach ($entrada as $clave => $valor) {
            if (count($limpia) === self::MAX_AJUSTES) {
                break;
            }
            if (is_string($clave) && preg_match(self::CLAVE, $clave) === 1 && self::valorValido($valor)) {
                $limpia[$clave] = $valor;
            }
        }

        return $limpia;
    }

    private static function valorValido(mixed $valor): bool
    {
        return match (true) {
            is_bool($valor) => true,
            is_int($valor) => abs($valor) <= self::MAX_NUMERO,
            is_float($valor) => is_finite($valor) && abs($valor) <= self::MAX_NUMERO,
            is_string($valor) => preg_match(self::TEXTO, $valor) === 1,
            default => false,
        };
    }
}
