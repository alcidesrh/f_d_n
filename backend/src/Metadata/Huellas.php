<?php

declare(strict_types=1);

namespace App\Metadata;

/**
 * Huella (sha256) de lo que el navegador guarda: el mismo contenido da la
 * misma huella sin importar el orden de las claves de cada objeto. Las listas
 * conservan su orden (el de las columnas importa).
 */
final class Huellas
{
    public static function de(mixed $valor): string
    {
        return hash("sha256", json_encode(self::canonico($valor), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
    }

    private static function canonico(mixed $valor): mixed
    {
        if (!is_array($valor)) {
            return $valor;
        }
        $valor = array_map(self::canonico(...), $valor);
        if (!array_is_list($valor)) {
            ksort($valor, SORT_STRING);
        }

        return $valor;
    }
}
