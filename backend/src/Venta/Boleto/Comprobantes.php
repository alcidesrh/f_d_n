<?php

declare(strict_types=1);

namespace App\Venta\Boleto;

use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Venta\HorasSalida;

/**
 * `DatosBoleto` con la hora de salida estimada en la parada donde sube el
 * pasajero (`salida.salidaOrigen`), en cada viaje del comprobante, y el
 * código de abordaje de cada boleto (`codigoAbordaje`, ADR-063).
 */
final class Comprobantes
{
    public function __construct(
        private readonly HorasSalida $horas,
        private readonly LlaveBoleto $llaves,
    ) {}

    /**
     * @param list<int>|null $soloIds boletos que se imprimen; por defecto, los vivos de la venta
     *
     * @return array<string, mixed>
     */
    public function de(BoletoVenta $venta, ?array $soloIds = null): array
    {
        $datos = DatosBoleto::de(
            $venta,
            fn(BoletoAsiento $b) => $this->horas->subida($b->getSalida(), (int) $b->getTrayecto()->getOrigen()->getId()),
            $soloIds,
        );
        // El código de abordaje de cada boleto (ADR-063): lo que lleva su QR en el PDF y en el ticket.
        $conCodigo = fn(array $b): array => [...$b, "codigoAbordaje" => $this->llaves->codigo((int) $b["id"])];
        $datos["boletos"] = array_map($conCodigo, $datos["boletos"]);
        foreach ($datos["viajes"] as $i => $viaje) {
            $datos["viajes"][$i]["boletos"] = array_map($conCodigo, $viaje["boletos"]);
        }

        return $datos;
    }
}
