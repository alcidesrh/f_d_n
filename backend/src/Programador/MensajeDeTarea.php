<?php

declare(strict_types=1);

namespace App\Programador;

/**
 * Mensaje de una tarea del catálogo (ADR-044). Al consumirlo, el worker
 * (`TareasMiddleware`) toma un cerrojo con su `clave()` —si otra corrida
 * sigue, esta se omite— y anota el latido de la tarea. Su handler devuelve
 * un resumen corto (lo que hizo) que queda en el estado de la tarea.
 */
interface MensajeDeTarea
{
    public function tarea(): Tarea;
}
