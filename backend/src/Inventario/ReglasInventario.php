<?php

declare(strict_types=1);

namespace App\Inventario;

use App\Acceso\Capacidad;
use App\Acceso\Dimension;
use App\Acceso\Ubicacion;
use App\Entity\Almacen;
use App\Entity\Existencia;

/**
 * Reglas del inventario (ADR-071), puras: en qué estado está una
 * existencia, cuánto reponer, dónde está un almacén para el alcance y los
 * textos de la afectación del faltante.
 */
final class ReglasInventario
{
    public const AGOTADO = "agotado";
    public const BAJO_MINIMO = "bajo_minimo";
    public const POR_REPONER = "por_reponer";
    public const BIEN = "bien";

    /** Centésimas: sin nada, agotado; debajo del mínimo; en el punto de reorden o debajo; bien. */
    public static function estado(int $cantidad, int $minimo, ?int $puntoReorden): string
    {
        return match (true) {
            $cantidad <= 0 => self::AGOTADO,
            $cantidad < $minimo => self::BAJO_MINIMO,
            $puntoReorden !== null && $cantidad <= $puntoReorden => self::POR_REPONER,
            default => self::BIEN,
        };
    }

    /** ¿Crea (o mantiene) la afectación del faltante? Solo con un mínimo puesto. */
    public static function faltante(int $cantidad, int $minimo): bool
    {
        return $minimo > 0 && $cantidad < $minimo;
    }

    /**
     * Cuánto pedir para llenar: hasta el máximo; sin máximo, hasta el punto
     * de reorden o el mínimo, el mayor. 0 si no hace falta.
     */
    public static function reponer(int $cantidad, int $minimo, ?int $puntoReorden, ?int $maximo): int
    {
        $tope = $maximo ?? max($minimo, $puntoReorden ?? 0);
        $hace = self::estado($cantidad, $minimo, $puntoReorden) !== self::BIEN && ($minimo > 0 || $puntoReorden !== null || $maximo !== null);

        return $hace ? max(0, $tope - $cantidad) : 0;
    }

    /** Dónde está el almacén para una capacidad: solo las dimensiones que esa capacidad mira. */
    public static function ubicacion(Capacidad $capacidad, Almacen $almacen): Ubicacion
    {
        $dimensiones = $capacidad->dimensiones();
        $u = in_array(Dimension::EMPRESA, $dimensiones, true) ? Ubicacion::empresa($almacen->getEmpresa()?->getId()) : Ubicacion::ninguna();

        return in_array(Dimension::ESTACION, $dimensiones, true) ? $u->conEstacion($almacen->getEstacion()?->getId()) : $u;
    }

    /** Lo que identifica el faltante para el detector (`Afectaciones::detectar`). */
    public static function clave(Existencia $e): string
    {
        return sprintf("existencia:%d:%d", $e->getAlmacen()->getId(), $e->getArticulo()->getId());
    }

    /** «Quedan 2.00 rollo de «Papel térmico» en Estación Central; el mínimo es 10.00.» */
    public static function avisoFaltante(Existencia $e): string
    {
        $a = $e->getArticulo();

        return sprintf(
            "%s de «%s» en %s; el mínimo es %s.",
            $e->centesimas() <= 0 ? "No queda nada" : sprintf("Quedan %s %s", Cantidad::texto($e->centesimas()), $a->getUnidad()),
            $a->getNombre(),
            $e->getAlmacen()->getNombre(),
            Cantidad::texto($e->minimoCentesimas()),
        );
    }

    /** «Se repuso: hay 12.00 rollo (mínimo 10.00).» o, sin mínimo, «Ya no tiene mínimo: hay 3.00 rollo.» */
    public static function avisoRepuesto(Existencia $e): string
    {
        $hay = sprintf("%s %s", Cantidad::texto($e->centesimas()), $e->getArticulo()->getUnidad());

        return $e->minimoCentesimas() > 0
            ? sprintf("Se repuso: hay %s (mínimo %s).", $hay, Cantidad::texto($e->minimoCentesimas()))
            : sprintf("Ya no tiene mínimo: hay %s.", $hay);
    }
}
