<?php

declare(strict_types=1);

namespace App\Venta\Boleto;

/**
 * Código QR en SVG (sin dependencias): modo byte, nivel de
 * corrección M y versiones 1 a 6 (hasta 106 bytes), de sobra para el código
 * de abordaje de un boleto (`LlaveBoleto`). ISO/IEC 18004.
 */
final class CodigoQr
{
    /** Por versión: [bloques, codewords de datos por bloque, de corrección por bloque] (nivel M). */
    private const BLOQUES = [
        1 => [1, 16, 10],
        2 => [1, 28, 16],
        3 => [1, 44, 26],
        4 => [2, 32, 18],
        5 => [2, 43, 24],
        6 => [4, 27, 16],
    ];

    /** Nivel M en la información de formato. */
    private const NIVEL_M = 0;

    /**
     * La matriz del código: `true` = módulo oscuro. Sin margen.
     *
     * @return list<list<bool>>
     */
    public static function matriz(string $texto): array
    {
        $version = self::version(strlen($texto));
        $lado = 17 + 4 * $version;
        $codewords = self::codewords($texto, $version);

        /** @var list<list<bool>> $modulos */
        $modulos = array_fill(0, $lado, array_fill(0, $lado, false));
        /** @var list<list<bool>> $funcion */
        $funcion = array_fill(0, $lado, array_fill(0, $lado, false));
        self::patrones($modulos, $funcion, $version);
        self::colocar($modulos, $funcion, $codewords);

        $mejor = null;
        $menor = PHP_INT_MAX;
        for ($mascara = 0; $mascara < 8; $mascara++) {
            $candidata = self::enmascarar($modulos, $funcion, $mascara);
            self::formato($candidata, $mascara);
            $penalizacion = self::penalizacion($candidata);
            if ($penalizacion < $menor) {
                $menor = $penalizacion;
                $mejor = $candidata;
            }
        }

        return $mejor ?? $modulos;
    }

    /**
     * SVG con 4 módulos de margen. Las medidas van en píxeles (`$modulo` por
     * módulo), sin escalar con `viewBox`: Dompdf no lo aplica bien.
     */
    public static function svg(string $texto, int $modulo = 4): string
    {
        $matriz = self::matriz($texto);
        $n = count($matriz);
        $rects = "";
        foreach ($matriz as $y => $fila) {
            for ($x = 0; $x < $n; $x++) {
                if (!$fila[$x]) {
                    continue;
                }
                $ancho = 1;
                while ($x + $ancho < $n && $fila[$x + $ancho]) {
                    $ancho++;
                }
                $rects .= sprintf('<rect x="%d" y="%d" width="%d" height="%d"/>', ($x + 4) * $modulo, ($y + 4) * $modulo, $ancho * $modulo, $modulo);
                $x += $ancho - 1;
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %1$d %1$d" width="%1$d" height="%1$d" shape-rendering="crispEdges"><rect width="%1$d" height="%1$d" fill="#fff"/><g fill="#000">%2$s</g></svg>',
            ($n + 8) * $modulo,
            $rects,
        );
    }

    /** La versión más chica en la que caben `$bytes` (4 bits de modo + 8 de largo + datos). */
    public static function version(int $bytes): int
    {
        foreach (self::BLOQUES as $version => [$bloques, $datos]) {
            if ($bytes <= $bloques * $datos - 2) {
                return $version;
            }
        }
        throw new \InvalidArgumentException("El texto no cabe en un código QR versión 6-M (106 bytes).");
    }

    /**
     * Los codewords finales: datos y corrección, intercalados por bloque.
     *
     * @return list<int>
     */
    public static function codewords(string $texto, int $version): array
    {
        [$bloques, $porBloque, $correccion] = self::BLOQUES[$version];
        $capacidad = $bloques * $porBloque;

        // Modo byte (0100), largo en 8 bits, datos y terminador (0000).
        $bits = "0100" . sprintf("%08b", strlen($texto));
        foreach (str_split($texto) as $c) {
            $bits .= sprintf("%08b", ord($c));
        }
        $bits .= str_repeat("0", min(4, $capacidad * 8 - strlen($bits)));
        $bits .= str_repeat("0", (8 - strlen($bits) % 8) % 8);
        $datos = array_map(static fn(string $b) => (int) bindec($b), str_split($bits, 8));
        for ($relleno = 0xEC; count($datos) < $capacidad; $relleno ^= 0xEC ^ 0x11) {
            $datos[] = $relleno;
        }

        $generador = self::generador($correccion);
        $grupos = array_chunk($datos, $porBloque);
        $paridades = array_map(static fn(array $g) => self::resto($g, $generador), $grupos);

        $final = [];
        for ($i = 0; $i < $porBloque; $i++) {
            foreach ($grupos as $g) {
                $final[] = $g[$i];
            }
        }
        for ($i = 0; $i < $correccion; $i++) {
            foreach ($paridades as $p) {
                $final[] = $p[$i];
            }
        }

        return $final;
    }

    /** Producto en GF(2^8) con el polinomio 0x11D. */
    private static function producto(int $a, int $b): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($b >> $i) & 1) * $a;
        }

        return $z;
    }

    /**
     * Coeficientes del polinomio generador de Reed-Solomon (sin el término mayor).
     *
     * @return list<int>
     */
    private static function generador(int $grado): array
    {
        $g = array_fill(0, $grado, 0);
        $g[$grado - 1] = 1;
        $raiz = 1;
        for ($i = 0; $i < $grado; $i++) {
            for ($j = 0; $j < $grado; $j++) {
                $g[$j] = self::producto($g[$j], $raiz);
                if ($j + 1 < $grado) {
                    $g[$j] ^= $g[$j + 1];
                }
            }
            $raiz = self::producto($raiz, 2);
        }

        return $g;
    }

    /**
     * @param list<int> $datos
     * @param list<int> $generador
     *
     * @return list<int>
     */
    private static function resto(array $datos, array $generador): array
    {
        $resto = array_fill(0, count($generador), 0);
        foreach ($datos as $d) {
            $factor = $d ^ array_shift($resto);
            $resto[] = 0;
            foreach ($generador as $i => $coef) {
                $resto[$i] ^= self::producto($coef, $factor);
            }
        }

        return $resto;
    }

    /**
     * Buscadores, separadores, sincronía, alineación, módulo oscuro y la
     * reserva de la información de formato.
     *
     * @param list<list<bool>> $m
     * @param list<list<bool>> $f
     */
    private static function patrones(array &$m, array &$f, int $version): void
    {
        $lado = count($m);
        $poner = static function (int $x, int $y, bool $oscuro) use (&$m, &$f, $lado): void {
            if ($x >= 0 && $x < $lado && $y >= 0 && $y < $lado) {
                $m[$y][$x] = $oscuro;
                $f[$y][$x] = true;
            }
        };

        for ($i = 0; $i < $lado; $i++) {
            $poner(6, $i, $i % 2 === 0);
            $poner($i, 6, $i % 2 === 0);
        }
        foreach ([[3, 3], [$lado - 4, 3], [3, $lado - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $d = max(abs($dx), abs($dy));
                    $poner($cx + $dx, $cy + $dy, $d !== 2 && $d !== 4);
                }
            }
        }
        if ($version >= 2) {
            $c = $lado - 7;
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    $poner($c + $dx, $c + $dy, max(abs($dx), abs($dy)) !== 1);
                }
            }
        }
        // Reserva del formato (se escribe con cada máscara) y módulo oscuro.
        for ($i = 0; $i < 9; $i++) {
            if (!$f[8][$i]) {
                $poner($i, 8, false);
            }
            if (!$f[$i][8]) {
                $poner(8, $i, false);
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $poner($lado - 1 - $i, 8, false);
            $poner(8, $lado - 1 - $i, false);
        }
        $poner(8, $lado - 8, true);
    }

    /**
     * Los bits de los codewords en zigzag, de abajo a la derecha hacia arriba.
     *
     * @param list<list<bool>> $m
     * @param list<list<bool>> $f
     * @param list<int> $codewords
     */
    private static function colocar(array &$m, array $f, array $codewords): void
    {
        $lado = count($m);
        $total = count($codewords) * 8;
        $i = 0;
        for ($derecha = $lado - 1; $derecha >= 1; $derecha -= 2) {
            if ($derecha === 6) {
                $derecha = 5;
            }
            for ($v = 0; $v < $lado; $v++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $derecha - $j;
                    $y = (($derecha + 1) & 2) === 0 ? $lado - 1 - $v : $v;
                    if (!$f[$y][$x] && $i < $total) {
                        $m[$y][$x] = (($codewords[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    /**
     * @param list<list<bool>> $m
     * @param list<list<bool>> $f
     *
     * @return list<list<bool>>
     */
    private static function enmascarar(array $m, array $f, int $mascara): array
    {
        foreach ($m as $y => $fila) {
            foreach ($fila as $x => $oscuro) {
                if ($f[$y][$x]) {
                    continue;
                }
                $invertir = match ($mascara) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => $x * $y % 2 + $x * $y % 3 === 0,
                    6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
                    default => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
                };
                $m[$y][$x] = $oscuro !== $invertir;
            }
        }

        return $m;
    }

    /**
     * Los 15 bits de formato (nivel y máscara, con su BCH), en sus dos copias.
     *
     * @param list<list<bool>> $m
     */
    private static function formato(array &$m, int $mascara): void
    {
        $lado = count($m);
        $datos = (self::NIVEL_M << 3) | $mascara;
        $resto = $datos;
        for ($i = 0; $i < 10; $i++) {
            $resto = ($resto << 1) ^ (($resto >> 9) * 0x537);
        }
        $bits = (($datos << 10) | $resto) ^ 0x5412;
        $bit = static fn(int $i): bool => (($bits >> $i) & 1) === 1;

        for ($i = 0; $i <= 5; $i++) {
            $m[$i][8] = $bit($i);
        }
        $m[7][8] = $bit(6);
        $m[8][8] = $bit(7);
        $m[8][7] = $bit(8);
        for ($i = 9; $i < 15; $i++) {
            $m[8][14 - $i] = $bit($i);
        }
        for ($i = 0; $i < 8; $i++) {
            $m[8][$lado - 1 - $i] = $bit($i);
        }
        for ($i = 8; $i < 15; $i++) {
            $m[$lado - 15 + $i][8] = $bit($i);
        }
    }

    /**
     * Las cuatro reglas de penalización para elegir la máscara.
     *
     * @param list<list<bool>> $m
     */
    private static function penalizacion(array $m): int
    {
        $lado = count($m);
        $total = 0;
        $lineas = $m;
        for ($x = 0; $x < $lado; $x++) {
            $lineas[] = array_column($m, $x);
        }
        foreach ($lineas as $linea) {
            // Regla 1: corridas de 5 o más del mismo color.
            $corrida = 1;
            for ($i = 1; $i <= $lado; $i++) {
                if ($i < $lado && $linea[$i] === $linea[$i - 1]) {
                    $corrida++;
                    continue;
                }
                if ($corrida >= 5) {
                    $total += $corrida - 2;
                }
                $corrida = 1;
            }
            // Regla 3: algo que se parece a un buscador.
            $texto = implode("", array_map(static fn(bool $b) => $b ? "1" : "0", $linea));
            $total += 40 * (substr_count($texto, "10111010000") + substr_count($texto, "00001011101"));
        }
        // Regla 2: bloques de 2×2.
        $oscuros = 0;
        for ($y = 0; $y < $lado; $y++) {
            for ($x = 0; $x < $lado; $x++) {
                $oscuros += $m[$y][$x] ? 1 : 0;
                if ($x + 1 < $lado && $y + 1 < $lado
                    && $m[$y][$x] === $m[$y][$x + 1] && $m[$y][$x] === $m[$y + 1][$x] && $m[$y][$x] === $m[$y + 1][$x + 1]) {
                    $total += 3;
                }
            }
        }
        // Regla 4: proporción de oscuros lejos del 50 %.
        $total += 10 * intdiv((int) abs($oscuros * 20 - $lado * $lado * 10), $lado * $lado);

        return $total;
    }
}
