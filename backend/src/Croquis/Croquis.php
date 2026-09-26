<?php

declare(strict_types=1);

namespace App\Croquis;

use App\Entity\Enum\AsientoClase;
use App\Entity\Enum\TipoBusSenal;

/**
 * Reglas puras del croquis de un bus: una rejilla por planta (hasta dos) de
 * `columnas × filas`, con coordenadas desde 1, donde cada celda tiene como
 * mucho un elemento (asiento, chofer o puerta).
 *
 * La rejilla no se guarda: sus dimensiones salen de los elementos. En el
 * legado las coordenadas eran múltiplos de 50 desde 0; `desdeLegado()` las
 * convierte.
 */
final class Croquis
{
    public const PLANTAS_MAX = 2;
    public const FILAS_MAX = 30;
    public const COLUMNAS_MAX = 6;
    public const NUMERO_MAX = 999;

    /** Paso de las coordenadas del legado (`coordenadaX/Y`: 0, 50, 100, …). */
    public const PASO_LEGADO = 50;

    /** Coordenada del legado (0, 50, 100, …) → consecutiva desde 1: `X / 50 + 1`. */
    public static function desdeLegado(int|string|null $valor): int
    {
        return intdiv(max(0, (int) $valor), self::PASO_LEGADO) + 1;
    }

    /**
     * Valida el cuerpo de `PUT /api/buses/{id}/croquis`
     * (`{ elementos: [{ tipo, id?, planta, fila, columna, numero?, clase? }] }`).
     *
     * @return list<ElementoCroquis>
     *
     * @throws InvalidCroquis con el primer problema encontrado
     */
    public static function desdeArray(mixed $payload): array
    {
        if (!is_array($payload) || !array_key_exists("elementos", $payload)) {
            throw new InvalidCroquis("Se esperaba { elementos: [...] }.");
        }
        $filas = $payload["elementos"];
        if (!is_array($filas) || !array_is_list($filas)) {
            throw new InvalidCroquis("`elementos` debe ser una lista.");
        }

        $elementos = [];
        foreach ($filas as $i => $fila) {
            $elementos[] = self::elemento($fila, $i);
        }
        self::validar($elementos);

        return $elementos;
    }

    /**
     * Invariantes del conjunto: celdas únicas, números de asiento únicos, un
     * solo chofer e ids sin repetir.
     *
     * @param list<ElementoCroquis> $elementos
     *
     * @throws InvalidCroquis
     */
    public static function validar(array $elementos): void
    {
        $celdas = [];
        $numeros = [];
        $ids = [];
        $choferes = 0;
        foreach ($elementos as $elemento) {
            $celda = $elemento->celda();
            if (isset($celdas[$celda])) {
                throw new InvalidCroquis(
                    sprintf(
                        "Dos elementos en la planta %d, fila %d, columna %d.",
                        $elemento->planta,
                        $elemento->fila,
                        $elemento->columna,
                    ),
                );
            }
            $celdas[$celda] = true;

            if ($elemento->esAsiento()) {
                if (isset($numeros[$elemento->numero])) {
                    throw new InvalidCroquis(
                        sprintf(
                            "El número de asiento %d está repetido.",
                            $elemento->numero,
                        ),
                    );
                }
                $numeros[$elemento->numero] = true;
            } elseif ($elemento->tipo === TipoBusSenal::CHOFER->value) {
                if (++$choferes > 1) {
                    throw new InvalidCroquis("El bus solo puede tener un chofer.");
                }
            }

            if ($elemento->id !== null) {
                $clave = ($elemento->esAsiento() ? "a" : "s") . $elemento->id;
                if (isset($ids[$clave])) {
                    throw new InvalidCroquis(
                        sprintf("El elemento %d aparece dos veces.", $elemento->id),
                    );
                }
                $ids[$clave] = true;
            }
        }
    }

    /**
     * Elementos en orden de lectura: planta, fila, columna.
     *
     * @param list<ElementoCroquis> $elementos
     *
     * @return list<ElementoCroquis>
     */
    public static function ordenar(array $elementos): array
    {
        usort(
            $elementos,
            static fn(ElementoCroquis $a, ElementoCroquis $b) => [
                $a->planta,
                $a->fila,
                $a->columna,
            ] <=> [$b->planta, $b->fila, $b->columna],
        );

        return $elementos;
    }

    /**
     * Huella de la distribución (sin ids): dos buses con la misma firma tienen
     * el mismo croquis. Sirve para agrupar plantillas.
     *
     * @param list<ElementoCroquis> $elementos
     */
    public static function firma(array $elementos): string
    {
        $partes = array_map(
            static fn(ElementoCroquis $e) => $e->celda() .
                ":" .
                $e->tipo .
                ($e->esAsiento() ? ":" . $e->numero . ":" . $e->clase?->value : ""),
            self::ordenar($elementos),
        );

        return sha1(implode("|", $partes));
    }

    private static function elemento(mixed $fila, int $i): ElementoCroquis
    {
        $donde = sprintf("Elemento %d", $i + 1);
        if (!is_array($fila)) {
            throw new InvalidCroquis("{$donde}: se esperaba un objeto.");
        }

        $tipo = $fila["tipo"] ?? null;
        if (
            $tipo !== ElementoCroquis::ASIENTO &&
            TipoBusSenal::tryFrom((string) $tipo) === null
        ) {
            throw new InvalidCroquis(
                sprintf('%s: tipo desconocido "%s".', $donde, (string) $tipo),
            );
        }

        $planta = self::entero($fila, "planta", 1, self::PLANTAS_MAX, $donde);
        $filaN = self::entero($fila, "fila", 1, self::FILAS_MAX, $donde);
        $columna = self::entero($fila, "columna", 1, self::COLUMNAS_MAX, $donde);
        $id = isset($fila["id"])
            ? self::entero($fila, "id", 1, PHP_INT_MAX, $donde)
            : null;

        if ($tipo !== ElementoCroquis::ASIENTO) {
            return new ElementoCroquis($tipo, $planta, $filaN, $columna, id: $id);
        }

        $numero = self::entero($fila, "numero", 1, self::NUMERO_MAX, $donde);
        $clase = AsientoClase::tryFrom((string) ($fila["clase"] ?? ""));
        if ($clase === null) {
            throw new InvalidCroquis(
                "{$donde}: la clase del asiento debe ser A o B.",
            );
        }

        return new ElementoCroquis(
            $tipo,
            $planta,
            $filaN,
            $columna,
            $numero,
            $clase,
            $id,
        );
    }

    /**
     * @param array<mixed> $fila
     */
    private static function entero(
        array $fila,
        string $campo,
        int $min,
        int $max,
        string $donde,
    ): int {
        $valor = $fila[$campo] ?? null;
        if (!is_int($valor) || $valor < $min || $valor > $max) {
            throw new InvalidCroquis(
                $max === PHP_INT_MAX
                    ? sprintf("%s: `%s` debe ser un entero ≥ %d.", $donde, $campo, $min)
                    : sprintf(
                        "%s: `%s` debe ser un entero entre %d y %d.",
                        $donde,
                        $campo,
                        $min,
                        $max,
                    ),
            );
        }

        return $valor;
    }
}
