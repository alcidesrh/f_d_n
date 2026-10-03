<?php

declare(strict_types=1);

namespace App\Seguimiento;

/**
 * Punto de enchufe para el GPS real. Mientras no haya dispositivos, `SinGps`
 * no devuelve nada y todas las posiciones se simulan; para pasar a datos
 * reales basta con implementar esta interfaz (leer de la tabla o cola donde
 * lleguen las tramas) y devolver la última lectura del bus. `SeguimientoBuses`
 * la prefiere a la simulación mientras sea reciente.
 */
interface FuenteGps
{
    /** Última lectura del bus (código del legado), o null si no tiene. La posición debe traer `fuente: 'gps'`. */
    public function ultima(string $busCodigo): ?Posicion;
}
