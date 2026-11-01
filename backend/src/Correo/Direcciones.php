<?php

declare(strict_types=1);

namespace App\Correo;

/**
 * Reglas puras sobre direcciones de correo: leer listas como las de una
 * cabecera (`Ana <ana@x.com>, "Pérez, Juan" <juan@y.com>, z@w.com`),
 * validar, y sacar del nombre de usuario la parte local de su cuenta.
 */
final class Direcciones
{
    /**
     * @return list<array{direccion: string, nombre: ?string}> sin repetidas ni inválidas
     */
    public static function leer(?string $lista): array
    {
        if ($lista === null || trim($lista) === "") {
            return [];
        }
        $resultado = [];
        foreach (self::partir($lista) as $parte) {
            $parte = trim($parte);
            if ($parte === "") {
                continue;
            }
            if (preg_match('/^(.*)<([^<>]+)>\s*$/s', $parte, $m)) {
                $nombre = trim(trim($m[1]), "\"' ");
                $direccion = trim($m[2]);
            } else {
                $nombre = "";
                $direccion = trim($parte, "\"' ");
            }
            $direccion = strtolower($direccion);
            if (!self::valida($direccion) || isset($resultado[$direccion])) {
                continue;
            }
            $resultado[$direccion] = ["direccion" => $direccion, "nombre" => $nombre !== "" ? str_replace(["\r", "\n"], " ", $nombre) : null];
        }

        return array_values($resultado);
    }

    /**
     * Lo que manda el navegador: una lista de `{direccion, nombre?}` o de textos.
     *
     * @return list<array{direccion: string, nombre: ?string}>
     */
    public static function deEntrada(mixed $valor): array
    {
        if (!is_array($valor)) {
            return is_string($valor) ? self::leer($valor) : [];
        }
        $resultado = [];
        foreach ($valor as $v) {
            $d = is_array($v) ? ["direccion" => strtolower(trim((string) ($v["direccion"] ?? ""))), "nombre" => isset($v["nombre"]) && trim((string) $v["nombre"]) !== "" ? mb_substr(trim((string) $v["nombre"]), 0, 160) : null] : (self::leer((string) $v)[0] ?? null);
            if ($d !== null && self::valida($d["direccion"]) && !isset($resultado[$d["direccion"]])) {
                $resultado[$d["direccion"]] = $d;
            }
        }

        return array_values($resultado);
    }

    public static function valida(string $direccion): bool
    {
        return strlen($direccion) <= 254 && filter_var($direccion, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** @return array{0: string, 1: string} parte local y dominio, en minúsculas */
    public static function partes(string $direccion): array
    {
        $at = strrpos($direccion, "@");

        return $at === false ? [strtolower($direccion), ""] : [strtolower(substr($direccion, 0, $at)), strtolower(substr($direccion, $at + 1))];
    }

    /**
     * Parte local de la cuenta de un usuario: el nombre de usuario en
     * minúsculas, sin tildes, con lo que no sea letra, número, punto, guion o
     * guion bajo cambiado por un punto. Vacío si no queda nada.
     */
    public static function localDe(string $username): string
    {
        $base = strtolower(trim($username));
        $sinTildes = strtr($base, ["á" => "a", "é" => "e", "í" => "i", "ó" => "o", "ú" => "u", "ü" => "u", "ñ" => "n", "à" => "a", "è" => "e", "ì" => "i", "ò" => "o", "ù" => "u"]);
        $limpio = preg_replace('/[^a-z0-9._-]+/', ".", $sinTildes) ?? "";
        $limpio = preg_replace('/\.{2,}/', ".", $limpio) ?? "";

        return substr(trim($limpio, ".-_"), 0, 60);
    }

    /** `Nombre <direccion>` para una cabecera, o solo la dirección. */
    public static function formatear(string $direccion, ?string $nombre): string
    {
        return $nombre !== null && $nombre !== "" ? sprintf('"%s" <%s>', str_replace(['"', "\\"], "", $nombre), $direccion) : $direccion;
    }

    /**
     * Parte por comas que no estén entre comillas ni dentro de `< >`.
     *
     * @return list<string>
     */
    private static function partir(string $lista): array
    {
        $partes = [];
        $actual = "";
        $comillas = false;
        $angulo = false;
        foreach (preg_split('//u', $lista, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $c) {
            if ($c === '"') {
                $comillas = !$comillas;
            } elseif ($c === "<" && !$comillas) {
                $angulo = true;
            } elseif ($c === ">" && !$comillas) {
                $angulo = false;
            } elseif (($c === "," || $c === ";") && !$comillas && !$angulo) {
                $partes[] = $actual;
                $actual = "";
                continue;
            }
            $actual .= $c;
        }
        $partes[] = $actual;

        return $partes;
    }
}
