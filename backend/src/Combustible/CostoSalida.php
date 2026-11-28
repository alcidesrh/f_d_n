<?php

declare(strict_types=1);

namespace App\Combustible;

use App\Entity\Salida;
use App\Repository\PrecioCombustibleRepository;

/**
 * Costo de combustible de una salida: los km de su ruta, el rendimiento de su
 * bus y el precio de su día (ADR-073). Lee los datos; el cálculo es
 * `CostoCombustible`.
 */
final class CostoSalida
{
    public function __construct(private readonly PrecioCombustibleRepository $precios) {}

    /** @return array<string, mixed> */
    public function de(Salida $salida): array
    {
        $bus = $salida->getBus();
        $fecha = $salida->getFecha();
        $tipo = $bus?->getCombustible();
        $precio = $fecha !== null && $tipo !== null ? $this->precios->vigente($fecha, $tipo) : null;

        return [
            ...CostoCombustible::calcular(
                kilometros: $salida->getRuta()?->getKilometros(),
                rendimientoKmGal: $bus !== null ? (float) $bus->getRendimientoKmGal() : null,
                precioGalon: $precio !== null ? (float) $precio->getPrecio() : null,
                precioFecha: $precio?->getFecha()?->format("Y-m-d"),
                dia: $fecha?->format("Y-m-d"),
            ),
            "combustible" => $tipo?->value,
        ];
    }
}
