<?php

declare(strict_types=1);

namespace App\Sincronizacion;

use App\Programador\MensajeDeTarea;
use App\Programador\Tarea;

/**
 * `Tarea::SINCRONIZAR_LEGADO`: una vuelta del `Ciclo` cada minuto (ADR-042,
 * ADR-044). Con el interruptor apagado no hace nada; el cerrojo de la tarea
 * evita que dos vueltas se pisen si una tarda más de un minuto.
 */
final class SincronizarLegado implements MensajeDeTarea
{
    public function tarea(): Tarea
    {
        return Tarea::SINCRONIZAR_LEGADO;
    }
}
