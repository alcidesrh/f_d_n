<?php

declare(strict_types=1);

namespace App\Venta\Boleto;

use App\Entity\BoletoVenta;
use App\Venta\HorasRecorrido;

/**
 * `DatosBoleto` con la hora de salida estimada en la parada donde sube el
 * pasajero (`recorrido.salidaOrigen`).
 */
final class Comprobantes
{
    public function __construct(
        private readonly HorasRecorrido $horas,
    ) {}

    /** @return array<string, mixed> */
    public function de(BoletoVenta $venta): array
    {
        $boleto = $venta->getAsientos()->first() ?: null;
        $salida = $boleto === null
            ? null
            : $this->horas->salidaDesde($boleto->getRecorrido(), (int) $boleto->getTrayecto()->getOrigen()->getId());

        return DatosBoleto::de($venta, $salida);
    }
}
