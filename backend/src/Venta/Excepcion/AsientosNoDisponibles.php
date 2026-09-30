<?php

declare(strict_types=1);

namespace App\Venta\Excepcion;

final class AsientosNoDisponibles extends VentaRechazada
{
    /** @param list<int> $numeros números de asiento */
    public static function numeros(array $numeros): self
    {
        sort($numeros);

        return new self(
            count($numeros) === 1
                ? sprintf("El asiento %d ya no está disponible para ese trayecto.", $numeros[0])
                : sprintf("Los asientos %s ya no están disponibles para ese trayecto.", implode(", ", $numeros)),
            "asientos_no_disponibles",
            409,
            ["asientos" => $numeros],
        );
    }
}
