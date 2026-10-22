<?php

declare(strict_types=1);

namespace App\Entity\Enum;

use App\Bitacora\Operacion;

/** Lo que se registra en la bitácora de un `Role` (ADR-039). */
enum OperacionRol: string implements Operacion
{
    case CREADO = "creado";
    case MODIFICADO = "modificado";
    case ELIMINADO = "eliminado";

    public function etiqueta(): string
    {
        return match ($this) {
            self::CREADO => "Rol creado",
            self::MODIFICADO => "Rol modificado",
            self::ELIMINADO => "Rol eliminado",
        };
    }
}
