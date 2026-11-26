<?php

declare(strict_types=1);

namespace App\Afectacion\Tarea;

use App\Programador\MensajeDeTarea;
use App\Programador\Tarea;

/** `Tarea::VIGILAR_AFECTACIONES`: el centinela de las afectaciones abiertas (ADR-070). */
final class VigilarAfectaciones implements MensajeDeTarea
{
    public function tarea(): Tarea
    {
        return Tarea::VIGILAR_AFECTACIONES;
    }
}
