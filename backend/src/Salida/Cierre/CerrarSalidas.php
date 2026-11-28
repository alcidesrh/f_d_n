<?php

declare(strict_types=1);

namespace App\Salida\Cierre;

use App\Programador\MensajeDeTarea;
use App\Programador\Tarea;

/** `Tarea::CERRAR_SALIDAS`: iniciar y finalizar por la hora las salidas que nadie marcó (ADR-074). */
final class CerrarSalidas implements MensajeDeTarea
{
    public function tarea(): Tarea
    {
        return Tarea::CERRAR_SALIDAS;
    }
}
