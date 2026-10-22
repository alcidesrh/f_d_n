<?php

declare(strict_types=1);

namespace App\Acceso;

/** Por dónde se acota una capacidad (ADR-039). */
enum Dimension: string
{
    /** La empresa dueña del registro (la de la salida, del bus, de la tarifa…). */
    case EMPRESA = 'empresa';
    /** La estación donde ocurre la operación: la de trabajo del usuario o donde se vendió. */
    case ESTACION = 'estacion';
}
