<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * De qué regla dispensa una autorización, una vez (ADR-040). El conjunto es
 * cerrado y cada capacidad declara cuáles admite (`Capacidad::dispensas`):
 *
 * - `permiso`: hacerlo sin tener la capacidad donde cae la operación.
 * - `limite`: pasar el límite diario que el rol le fija (`Limite`).
 * - `hora`: anular o reasignar un boleto cuya salida ya pasó su hora (sin
 *   finalizar ni anular): el pasajero que perdió el bus.
 */
enum Dispensa: string
{
    case PERMISO = "permiso";
    case LIMITE = "limite";
    case HORA = "hora";

    public function etiqueta(): string
    {
        return match ($this) {
            self::PERMISO => "Sin permiso",
            self::LIMITE => "Límite diario superado",
            self::HORA => "Fuera de hora",
        };
    }
}
