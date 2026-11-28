<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Tipos de combustible que publica el mercado nacional (MEM). Cada bus dice
 * cuál usa (`Bus.combustible`) y el precio se guarda por tipo (`PrecioCombustible`).
 */
enum TipoCombustible: string
{
    case DIESEL = "diesel";
    case REGULAR = "regular";
    case SUPERIOR = "superior";

    public function nombre(): string
    {
        return match ($this) {
            self::DIESEL => "Diésel",
            self::REGULAR => "Gasolina regular",
            self::SUPERIOR => "Gasolina superior",
        };
    }
}
