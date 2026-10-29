<?php

declare(strict_types=1);

namespace App\Salida\Horizonte;

use App\Programador\MensajeDeTarea;
use App\Programador\Tarea;

/**
 * `Tarea::HORIZONTE_SALIDAS`: cada madrugada, un `ExtenderHorizonte` por
 * esquema automático que no llega a su horizonte (cola `pesadas`, ADR-044).
 */
final class ExtenderHorizontes implements MensajeDeTarea
{
    public function tarea(): Tarea
    {
        return Tarea::HORIZONTE_SALIDAS;
    }
}
