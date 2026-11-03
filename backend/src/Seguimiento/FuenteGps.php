<?php

declare(strict_types=1);

namespace App\Seguimiento;

/**
 * Punto de enchufe para el GPS real. Hoy `GpsReciente`, que guarda lo que
 * envían los dispositivos a `/api/seguimiento/gps`; un bus sin lecturas se
 * simula. `SeguimientoBuses` prefiere la lectura a la simulación mientras sea
 * reciente.
 */
interface FuenteGps
{
    /** Última lectura del bus (código del legado), o null si no tiene. La posición debe traer `fuente: 'gps'`. */
    public function ultima(string $busCodigo): ?Posicion;
}
