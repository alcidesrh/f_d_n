<?php

declare(strict_types=1);

namespace App\Venta\Boleto;

use App\Entity\BoletoVenta;
use App\Venta\HorasSalida;

/**
 * `DatosBoleto` con la hora de salida estimada en la parada donde sube el
 * pasajero (`salida.salidaOrigen`).
 */
final class Comprobantes
{
    public function __construct(
        private readonly HorasSalida $horas,
    ) {}

    /** @return array<string, mixed> */
    public function de(BoletoVenta $venta): array
    {
        $boleto = $venta->getAsientos()->first() ?: null;
        $salida = $boleto === null
            ? null
            : $this->horas->salidaDesde($boleto->getSalida(), (int) $boleto->getTrayecto()->getOrigen()->getId());

        return DatosBoleto::de($venta, $salida);
    }
}
