<?php

declare(strict_types=1);

namespace App\Actividad;

/**
 * El nombre de la operación de una petición GraphQL: `operationName` o, si no
 * viene, el de la consulta (`query Salidas`) o su primer campo. Puro.
 */
final class OperacionGraphql
{
    /** @param array<mixed> $cuerpo */
    public static function nombre(array $cuerpo): ?string
    {
        $nombre = $cuerpo["operationName"] ?? null;
        $query = is_string($cuerpo["query"] ?? null) ? $cuerpo["query"] : "";
        $tipo = preg_match('/^\s*(query|mutation|subscription)\b/', $query, $m) ? $m[1] : "query";
        if (!is_string($nombre) || $nombre === "") {
            if (preg_match('/^\s*(?:query|mutation|subscription)\s+(\w+)/', $query, $m)) {
                $nombre = $m[1];
            } elseif (preg_match('/\{\s*(\w+)/', $query, $m)) {
                $nombre = $m[1];
            } else {
                return null;
            }
        }

        return mb_substr($tipo . " " . $nombre, 0, 60);
    }
}
