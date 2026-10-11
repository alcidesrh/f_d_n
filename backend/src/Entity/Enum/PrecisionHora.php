<?php

namespace App\Entity\Enum;

/** Qué tan segura es la hora de una salida en una parada (ADR-030). */
enum PrecisionHora: string
{
    /** La primera parada: la hora de la salida. */
    case EXACTA = "exacta";
    /** Sumando los minutos de la ruta hasta la parada. */
    case ESTIMADA = "estimada";
    /** No se conocen los minutos: se usa la hora del origen. */
    case DESCONOCIDA = "desconocida";
}
