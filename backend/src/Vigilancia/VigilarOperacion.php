<?php

declare(strict_types=1);

namespace App\Vigilancia;

use App\Programador\MensajeDeTarea;
use App\Programador\Tarea;

/** `Tarea::VIGILAR_OPERACION`: las alertas nuevas del tablero, a quien se suscribió (ADR-044). */
final class VigilarOperacion implements MensajeDeTarea
{
    public function tarea(): Tarea
    {
        return Tarea::VIGILAR_OPERACION;
    }
}
