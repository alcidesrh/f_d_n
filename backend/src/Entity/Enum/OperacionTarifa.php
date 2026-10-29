<?php

declare(strict_types=1);

namespace App\Entity\Enum;

use App\Bitacora\Operacion;

/** Lo que se registra en la bitácora de una `BoletoTarifa` (ADR-045). */
enum OperacionTarifa: string implements Operacion
{
    case CREADA = "creada";
    case MODIFICADA = "modificada";
    case ELIMINADA = "eliminada";

    public function etiqueta(): string
    {
        return match ($this) {
            self::CREADA => "Tarifa creada",
            self::MODIFICADA => "Tarifa modificada",
            self::ELIMINADA => "Tarifa eliminada",
        };
    }
}
