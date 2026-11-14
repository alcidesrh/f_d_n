<?php

declare(strict_types=1);

namespace App\Venta\Boleto;

use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Venta\HorasSalida;

/**
 * `DatosBoleto` con la hora de salida estimada en la parada donde sube el
 * pasajero (`salida.salidaOrigen`), en cada viaje del comprobante.
 */
final class Comprobantes
{
    public function __construct(
        private readonly HorasSalida $horas,
    ) {}

    /**
     * @param list<int>|null $soloIds boletos que se imprimen; por defecto, los vivos de la venta
     *
     * @return array<string, mixed>
     */
    public function de(BoletoVenta $venta, ?array $soloIds = null): array
    {
        return DatosBoleto::de(
            $venta,
            fn(BoletoAsiento $b) => $this->horas->subida($b->getSalida(), (int) $b->getTrayecto()->getOrigen()->getId()),
            $soloIds,
        );
    }
}
