<?php

declare(strict_types=1);

namespace App\Tablero\Tarea;

use App\Programador\MensajeDeTarea;
use App\Programador\Tarea;

/** `Tarea::REFRESCAR_TABLERO`: rehace las copias del tablero que alguien está mirando (ADR-044). */
final class RefrescarTablero implements MensajeDeTarea
{
    public function tarea(): Tarea
    {
        return Tarea::REFRESCAR_TABLERO;
    }
}
