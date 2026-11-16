<?php

declare(strict_types=1);

namespace App\Mantenimiento\Tarea;

use App\Programador\MensajeDeTarea;
use App\Programador\Tarea;

/** `Tarea::MANTENIMIENTO_FLOTA`: suma al odómetro lo recorrido por GPS y avisa lo que vence (ADR-065). */
final class RevisarMantenimiento implements MensajeDeTarea
{
    public function tarea(): Tarea
    {
        return Tarea::MANTENIMIENTO_FLOTA;
    }
}
