<?php

declare(strict_types=1);

namespace App\Sincronizacion;

use App\Programador\MensajeDeTarea;
use App\Programador\Tarea;

/**
 * `Tarea::BARRER_SALIDAS_LEGADO`: trae las salidas del legado que faltan aquí
 * y deja anotadas las que no pudo (ADR-042). Con el interruptor apagado no
 * hace nada.
 */
final class BarrerSalidasLegado implements MensajeDeTarea
{
    public function tarea(): Tarea
    {
        return Tarea::BARRER_SALIDAS_LEGADO;
    }
}
