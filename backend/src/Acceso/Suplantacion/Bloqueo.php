<?php

declare(strict_types=1);

namespace App\Acceso\Suplantacion;

/** Qué pasa con una petición hecha mientras se suplanta. */
enum Bloqueo
{
    /** Sigue como cualquier otra. */
    case NINGUNO;
    /** 403 con el motivo. */
    case PROHIBIDO;
    /** 204 sin hacer nada: lo que la app hace sola (presencia, leído, push) no debe fallar ni dejar rastro. */
    case IGNORAR;
}
