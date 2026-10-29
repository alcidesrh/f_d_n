<?php

declare(strict_types=1);

namespace App\Chat\Tarea;

use App\Programador\MensajeDeTarea;
use App\Programador\Tarea;

/** `Tarea::PURGAR_ARCHIVOS_CHAT`: archivos subidos al chat y nunca enviados (ADR-044). */
final class PurgarArchivosChat implements MensajeDeTarea
{
    /** Un archivo suelto se borra pasado esto (da tiempo a terminar el mensaje). */
    public const ANTIGUEDAD = '-24 hours';

    public function tarea(): Tarea
    {
        return Tarea::PURGAR_ARCHIVOS_CHAT;
    }
}
