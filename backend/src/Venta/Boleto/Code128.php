<?php

declare(strict_types=1);

namespace App\Venta\Boleto;

/**
 * Código de barras Code 128 en SVG (sin dependencias). Cadenas de dígitos
 * de largo par van en el subconjunto C (dos dígitos por símbolo); el resto,
 * en el B (ASCII 32–127). Espejo en `frontend/src/shared/barcode/code128.ts`.
 */
final class Code128
{
    /** Anchos barra/espacio de cada símbolo (0–105) y el de parada (106). */
    public const PATRONES = [
        "212222", "222122", "222221", "121223", "121322", "131222", "122213", "122312", "132212", "221213",
        "221312", "231212", "112232", "122132", "122231", "113222", "123122", "123221", "223211", "221132",
        "221231", "213212", "223112", "312131", "311222", "321122", "321221", "312212", "322112", "322211",
        "212123", "212321", "232121", "111323", "131123", "131321", "112313", "132113", "132311", "211313",
        "231113", "231311", "112133", "112331", "132131", "113123", "113321", "133121", "313121", "211331",
        "231131", "213113", "213311", "213131", "311123", "311321", "331121", "312113", "312311", "332111",
        "314111", "221411", "431111", "111224", "111422", "121124", "121421", "141122", "141221", "112214",
        "112412", "122114", "122411", "142112", "142211", "241211", "221114", "413111", "241112", "134111",
        "111242", "121142", "121241", "114212", "124112", "124211", "411212", "421112", "421211", "212141",
        "214121", "412121", "111143", "111341", "131141", "114113", "114311", "411113", "411311", "113141",
        "114131", "311141", "411131", "211412", "211214", "211232", "2331112",
    ];

    private const INICIO_B = 104;
    private const INICIO_C = 105;
    private const PARADA = 106;

    /**
     * Valores de símbolo, con inicio, checksum y parada.
     *
     * @return list<int>
     */
    public static function simbolos(string $texto): array
    {
        if ($texto !== "" && ctype_digit($texto) && strlen($texto) % 2 === 0) {
            $datos = array_map("intval", str_split($texto, 2));
            $inicio = self::INICIO_C;
        } else {
            $datos = [];
            foreach (str_split($texto) as $c) {
                $o = ord($c);
                if ($o < 32 || $o > 127) {
                    throw new \InvalidArgumentException("Code 128 B solo admite ASCII imprimible.");
                }
                $datos[] = $o - 32;
            }
            $inicio = self::INICIO_B;
        }

        $suma = $inicio;
        foreach ($datos as $i => $v) {
            $suma += $v * ($i + 1);
        }

        return [$inicio, ...$datos, $suma % 103, self::PARADA];
    }

    /**
     * Módulos del código: `true` = barra. Con 10 módulos de margen a cada lado.
     *
     * @return list<bool>
     */
    public static function modulos(string $texto): array
    {
        $modulos = array_fill(0, 10, false);
        foreach (self::simbolos($texto) as $s) {
            foreach (str_split(self::PATRONES[$s]) as $i => $ancho) {
                array_push($modulos, ...array_fill(0, (int) $ancho, $i % 2 === 0));
            }
        }

        return [...$modulos, ...array_fill(0, 10, false)];
    }

    public static function svg(string $texto, int $alto = 50): string
    {
        $modulos = self::modulos($texto);
        $rects = "";
        $n = count($modulos);
        for ($x = 0; $x < $n; $x++) {
            if (!$modulos[$x]) {
                continue;
            }
            $ancho = 1;
            while ($x + $ancho < $n && $modulos[$x + $ancho]) {
                $ancho++;
            }
            $rects .= sprintf('<rect x="%d" y="0" width="%d" height="%d"/>', $x, $ancho, $alto);
            $x += $ancho - 1;
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" shape-rendering="crispEdges"><rect width="100%%" height="100%%" fill="#fff"/><g fill="#000">%s</g></svg>',
            $n,
            $alto,
            $n * 2,
            $alto,
            $rects,
        );
    }
}
