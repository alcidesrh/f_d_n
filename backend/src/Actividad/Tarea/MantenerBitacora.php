<?php

declare(strict_types=1);

namespace App\Actividad\Tarea;

use App\Programador\MensajeDeTarea;
use App\Programador\Tarea;

/**
 * `Tarea::BITACORA_DIARIA`, cada hora: crea las particiones de `actividad`
 * que vienen, resume el día anterior pasada la hora configurada y borra lo
 * vencido según la retención (exportándolo antes si así está configurado).
 */
final class MantenerBitacora implements MensajeDeTarea
{
    public function tarea(): Tarea
    {
        return Tarea::BITACORA_DIARIA;
    }
}
