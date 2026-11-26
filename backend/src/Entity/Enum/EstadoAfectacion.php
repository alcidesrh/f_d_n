<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Ciclo de una afectación (ADR-070): `creada → pendiente → resuelta`.
 *
 * - `creada`: recién detectada; todavía no se avisó a nadie.
 * - `pendiente`: el centinela ya la avisó a quien la atiende (`target` del tipo).
 * - `resuelta`: quien la atiende la marcó como resuelta. También se resuelve
 *   desde `creada` (se arregló antes del primer aviso).
 */
enum EstadoAfectacion: string
{
    case CREADA = "creada";
    case PENDIENTE = "pendiente";
    case RESUELTA = "resuelta";

    public function etiqueta(): string
    {
        return match ($this) {
            self::CREADA => "Creada",
            self::PENDIENTE => "Pendiente",
            self::RESUELTA => "Resuelta",
        };
    }

    /** Todavía la vigila el centinela. */
    public function abierta(): bool
    {
        return $this !== self::RESUELTA;
    }
}
