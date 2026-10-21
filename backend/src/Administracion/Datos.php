<?php

declare(strict_types=1);

namespace App\Administracion;

/**
 * Lectura y validación de lo que llega por `/api/administracion` (puro):
 * textos recortados, ícono y color válidos, claves sin repetir, ids.
 */
final class Datos
{
    /** Rampas de color que el frontend pinta (`assets/palette.css`). */
    public const COLORES = ["blue", "sky", "cyan", "teal", "emerald", "green", "lime", "yellow", "amber", "orange", "red", "rose", "pink", "fuchsia", "purple", "violet", "indigo", "slate", "stone"];

    public static function texto(array $datos, string $campo, string $nombre, int $maximo = 80): string
    {
        $valor = $datos[$campo] ?? null;
        $valor = is_string($valor) ? trim(preg_replace('/\s+/', " ", $valor) ?? "") : "";
        if ($valor === "") {
            throw new AdministracionRechazada("Falta {$nombre}.", "{$campo}_requerido");
        }
        if (mb_strlen($valor) > $maximo) {
            throw new AdministracionRechazada("{$nombre} no puede pasar de {$maximo} caracteres.", "{$campo}_largo");
        }

        return $valor;
    }

    /** Nombre de Material Symbols: minúsculas, dígitos y guiones (`directions-bus-outline`). */
    public static function icono(array $datos): string
    {
        $icono = $datos["icono"] ?? null;
        if (!is_string($icono) || !preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $icono) || strlen($icono) > 80) {
            throw new AdministracionRechazada("Elegí un ícono.", "icono_invalido");
        }

        return $icono;
    }

    public static function color(array $datos): string
    {
        $color = $datos["color"] ?? null;
        if (!is_string($color) || !in_array($color, self::COLORES, true)) {
            throw new AdministracionRechazada("Elegí un color de la paleta.", "color_invalido");
        }

        return $color;
    }

    /** @return list<string> sin vacías ni repetidas (sin distinguir mayúsculas), hasta 20. */
    public static function claves(array $datos): array
    {
        $claves = $datos["claves"] ?? [];
        if (!is_array($claves)) {
            return [];
        }
        $vistas = [];
        foreach ($claves as $clave) {
            $clave = is_string($clave) ? trim($clave) : "";
            if ($clave === "" || isset($vistas[mb_strtolower($clave)])) {
                continue;
            }
            $vistas[mb_strtolower($clave)] = mb_substr($clave, 0, 60);
        }

        return array_slice(array_values($vistas), 0, 20);
    }

    public static function booleano(array $datos, string $campo, bool $porDefecto): bool
    {
        return array_key_exists($campo, $datos) ? (bool) $datos[$campo] : $porDefecto;
    }

    /**
     * `acceso: { roles: [id], usuarios: [id] }`; `null` si no vino (no se toca).
     *
     * @return array{roles: list<int>, usuarios: list<int>}|null
     */
    public static function acceso(array $datos): ?array
    {
        if (!array_key_exists("acceso", $datos) || !is_array($datos["acceso"])) {
            return null;
        }

        return ["roles" => self::ids($datos["acceso"]["roles"] ?? []), "usuarios" => self::ids($datos["acceso"]["usuarios"] ?? [])];
    }

    /** @return list<int> */
    public static function ids(mixed $lista): array
    {
        if (!is_array($lista)) {
            return [];
        }

        return array_values(array_unique(array_map("intval", array_filter($lista, static fn($id) => is_int($id) || (is_string($id) && preg_match('/^-?\d+$/', $id))))));
    }

    /**
     * Orden completo: `{ secciones: [{ id, enlaces: [id] }] }`. Exige que
     * nombre cada caja y cada enlace una sola vez.
     *
     * @param list<int> $seccionesExistentes
     * @param list<int> $enlacesExistentes
     * @return list<array{id: int, enlaces: list<int>}>
     */
    public static function orden(array $datos, array $seccionesExistentes, array $enlacesExistentes): array
    {
        $orden = [];
        $secciones = [];
        $enlaces = [];
        foreach (is_array($datos["secciones"] ?? null) ? $datos["secciones"] : [] as $s) {
            $id = is_array($s) ? ($s["id"] ?? null) : null;
            if (!is_int($id)) {
                throw new AdministracionRechazada("El orden no es válido.", "orden_invalido");
            }
            $ids = self::ids($s["enlaces"] ?? []);
            $secciones[] = $id;
            array_push($enlaces, ...$ids);
            $orden[] = ["id" => $id, "enlaces" => $ids];
        }
        $iguales = static fn(array $a, array $b) => count($a) === count(array_unique($a)) && count($a) === count($b) && array_diff($a, $b) === [];
        if (!$iguales($secciones, $seccionesExistentes) || !$iguales($enlaces, $enlacesExistentes)) {
            throw new AdministracionRechazada("Las cajas cambiaron mientras ordenabas; recargá e intentá de nuevo.", "orden_desactualizado", 409);
        }

        return $orden;
    }
}
