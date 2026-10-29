<?php

declare(strict_types=1);

namespace App\Chat\Predeterminado\Tarea;

use App\Programador\MensajeDeTarea;
use App\Programador\Tarea;

/** `Tarea::REVISAR_ANOMALIAS`: reasignaciones repetidas y tarifas cambiadas del día anterior (ADR-045). */
final class RevisarAnomalias implements MensajeDeTarea
{
    public function tarea(): Tarea
    {
        return Tarea::REVISAR_ANOMALIAS;
    }
}
