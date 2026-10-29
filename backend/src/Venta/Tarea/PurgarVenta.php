<?php

declare(strict_types=1);

namespace App\Venta\Tarea;

use App\Programador\MensajeDeTarea;
use App\Programador\Tarea;

/** `Tarea::PURGAR_VENTA`: reservas web vencidas y ventas pendientes abandonadas (ADR-044). */
final class PurgarVenta implements MensajeDeTarea
{
    public function tarea(): Tarea
    {
        return Tarea::PURGAR_VENTA;
    }
}
