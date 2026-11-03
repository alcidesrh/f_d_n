<?php

declare(strict_types=1);

namespace App\Seguimiento\Tarea;

use App\Programador\MensajeDeTarea;
use App\Programador\Tarea;

/** `Tarea::PURGAR_LECTURAS_GPS`: lecturas del historial GPS más viejas que esto (ADR-050). */
final class PurgarLecturasGps implements MensajeDeTarea
{
    /** Cuánto se conservan las trayectorias para reproducirlas. */
    public const ANTIGUEDAD = '-90 days';

    public function tarea(): Tarea
    {
        return Tarea::PURGAR_LECTURAS_GPS;
    }
}
