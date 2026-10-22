<?php

declare(strict_types=1);

namespace App\Entity\Enum;

use App\Bitacora\Operacion;

/** Lo que se registra en la bitácora de una autorización (ADR-040). */
enum OperacionAutorizacion: string implements Operacion
{
    case SOLICITADA = "solicitada";
    case APROBADA = "aprobada";
    case RECHAZADA = "rechazada";
    case RETIRADA = "retirada";
    case USADA = "usada";

    public function etiqueta(): string
    {
        return match ($this) {
            self::SOLICITADA => "Autorización solicitada",
            self::APROBADA => "Autorización aprobada",
            self::RECHAZADA => "Autorización rechazada",
            self::RETIRADA => "Solicitud retirada",
            self::USADA => "Autorización usada",
        };
    }
}
