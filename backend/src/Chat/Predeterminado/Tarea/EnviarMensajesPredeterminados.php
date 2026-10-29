<?php

declare(strict_types=1);

namespace App\Chat\Predeterminado\Tarea;

use App\Programador\MensajeDeTarea;
use App\Programador\Tarea;

/** `Tarea::MENSAJES_PREDETERMINADOS`: los de hora y las salidas iniciadas (ADR-045). */
final class EnviarMensajesPredeterminados implements MensajeDeTarea
{
    public function tarea(): Tarea
    {
        return Tarea::MENSAJES_PREDETERMINADOS;
    }
}
