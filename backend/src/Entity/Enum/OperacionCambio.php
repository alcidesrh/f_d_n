<?php

declare(strict_types=1);

namespace App\Entity\Enum;

use App\Bitacora\Operacion;

/** Cambio de cualquier entidad sin bitácora propia (`App\Bitacora\RegistradorCambios`). */
enum OperacionCambio: string implements Operacion
{
    case CREADO = "creado";
    case MODIFICADO = "modificado";
    case ELIMINADO = "eliminado";

    public function etiqueta(): string
    {
        return match ($this) {
            self::CREADO => "Registro creado",
            self::MODIFICADO => "Registro modificado",
            self::ELIMINADO => "Registro eliminado",
        };
    }
}
