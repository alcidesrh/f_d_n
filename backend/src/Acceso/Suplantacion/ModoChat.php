<?php

declare(strict_types=1);

namespace App\Acceso\Suplantacion;

/** Qué puede hacer quien suplanta en el chat y el correo del usuario. */
enum ModoChat: string
{
    /** Como el usuario: leer y escribir (queda en la bitácora). */
    case PERMITIR = "permitir";
    /** Leer sin escribir, sin marcar como leído y sin aparecer en línea. */
    case LECTURA = "lectura";
    /** Ni leer ni escribir. */
    case BLOQUEAR = "bloquear";

    public function etiqueta(): string
    {
        return match ($this) {
            self::PERMITIR => "Leer y escribir como el usuario",
            self::LECTURA => "Solo lectura",
            self::BLOQUEAR => "Sin acceso",
        };
    }
}
