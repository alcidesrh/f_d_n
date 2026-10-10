<?php

declare(strict_types=1);

namespace App\Bitacora;

/** Operación que se anota en la bitácora (un enum por tipo de registro). */
interface Operacion extends \BackedEnum
{
    /** Texto para mostrar al usuario. */
    public function etiqueta(): string;
}
