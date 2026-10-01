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

    /**
     * Asientos ya ocupados en uno o más viajes de un carrito web.
     *
     * @param list<array{salida: int, viaje: int, asientos: list<int>, numeros: list<int>}> $viajes `viaje`: 0 = ida, 1 = regreso
     */
    public static function enViajes(array $viajes): self
    {
        $partes = [];
        foreach ($viajes as $v) {
            $numeros = $v["numeros"];
            sort($numeros);
            $partes[] = sprintf(
                "%s: %s",
                $v["viaje"] === 0 ? "ida" : "regreso",
                implode(", ", $numeros),
            );
        }

        return new self(
            sprintf("Mientras elegía, otra persona tomó estos asientos (%s). Elija otros.", implode("; ", $partes)),
            "asientos_no_disponibles",
            409,
            ["viajes" => $viajes],
        );
    }
}
