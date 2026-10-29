<?php

declare(strict_types=1);

namespace App\Reporte\Diario;

use App\Programador\MensajeDeTarea;
use App\Programador\Tarea;

/**
 * `Tarea::REPORTES_DIARIOS`: cada mañana, un `EnviarReporteDiario` por
 * destinatario y reporte del día anterior, a la cola `pesadas` (ADR-044).
 */
final class EnviarReportesDiarios implements MensajeDeTarea
{
    public function tarea(): Tarea
    {
        return Tarea::REPORTES_DIARIOS;
    }
}
