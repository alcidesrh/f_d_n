<?php

declare(strict_types=1);

namespace App\Actividad;

/**
 * Lo que va a `actividad.detalle`: solo los campos de la lista blanca, nunca
 * un secreto y acotado de tamaño. Puro.
 */
final class Detalle
{
    /** Claves que nunca se guardan aunque estén en la lista blanca (se anota que venían). */
    private const SECRETOS = '/pass|clave|token|secret|cvv|cvc|tarjeta|card|pan$|credencial|cifrad|authorization|cookie/i';

    /**
     * Deja solo las claves permitidas, en cualquier profundidad (los índices
     * de una lista se conservan). Un secreto queda como `"***"`.
     *
     * @param array<mixed>  $datos
     * @param list<string>  $permitidos
     *
     * @return array<mixed>
     */
    public static function filtrar(array $datos, array $permitidos, int $profundidad = 0): array
    {
        if ($profundidad > 6) {
            return [];
        }
        $lista = array_is_list($datos);
        $resultado = [];
        foreach ($datos as $clave => $valor) {
            if (!$lista) {
                if (preg_match(self::SECRETOS, (string) $clave)) {
                    $resultado[$clave] = "***";
                    continue;
                }
                if (!in_array((string) $clave, $permitidos, true)) {
                    continue;
                }
            }
            if (is_array($valor)) {
                $hijo = self::filtrar($valor, $permitidos, $profundidad + 1);
                // Un objeto sin campos permitidos no aporta; una lista sí (aunque quede vacía).
                if ($hijo !== [] || array_is_list($valor)) {
                    $resultado[$clave] = $hijo;
                }
            } elseif (is_scalar($valor) || $valor === null) {
                $resultado[$clave] = is_string($valor) ? mb_substr($valor, 0, 200) : $valor;
            }
        }

        return $lista ? array_values($resultado) : $resultado;
    }

    /**
     * Lo que manda la SPA (datos de pantalla, no del dominio): todo menos los
     * secretos, con los textos acotados.
     *
     * @param array<mixed> $datos
     *
     * @return array<mixed>
     */
    public static function sinSecretos(array $datos, int $profundidad = 0): array
    {
        if ($profundidad > 4) {
            return [];
        }
        $resultado = [];
        foreach ($datos as $clave => $valor) {
            if (is_string($clave) && preg_match(self::SECRETOS, $clave)) {
                $resultado[$clave] = "***";
            } elseif (is_array($valor)) {
                $resultado[$clave] = self::sinSecretos($valor, $profundidad + 1);
            } elseif (is_string($valor)) {
                $resultado[$clave] = mb_substr($valor, 0, 200);
            } elseif (is_scalar($valor) || $valor === null) {
                $resultado[$clave] = $valor;
            }
        }

        return $resultado;
    }

    /**
     * El JSON del detalle, o null si no hay nada. Si pasa del máximo, queda
     * solo qué claves tenía y cuánto medía.
     *
     * @param array<mixed> $detalle
     */
    public static function json(array $detalle, int $maxBytes): ?string
    {
        $detalle = array_filter($detalle, static fn(mixed $v) => $v !== null && $v !== []);
        if ($detalle === []) {
            return null;
        }
        $json = json_encode($detalle, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        if ($json === false) {
            return null;
        }
        if (strlen($json) <= $maxBytes) {
            return $json;
        }
        $resumen = json_encode(["recortado" => true, "bytes" => strlen($json), "claves" => array_slice(array_map("strval", array_keys($detalle)), 0, 30)], JSON_UNESCAPED_UNICODE);

        return strlen((string) $resumen) <= $maxBytes ? (string) $resumen : '{"recortado":true}';
    }
}
