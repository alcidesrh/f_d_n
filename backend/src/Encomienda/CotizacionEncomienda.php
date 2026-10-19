<?php

declare(strict_types=1);

namespace App\Encomienda;

use App\Venta\Boleto\DatosBoleto;
use Money\Money;

/** Precio de cada artículo de una encomienda, el descuento por bultos y el total (ADR-035). */
final readonly class CotizacionEncomienda
{
    /**
     * @param list<array{tarifa: Money, recargo: Money, cantidad: int, importe: Money}> $lineas en el orden pedido
     */
    public function __construct(
        public array $lineas,
        public Money $subtotal,
        public int $porcentajeDescuento,
        public Money $descuento,
        public Money $total,
    ) {}

    public function bultos(): int
    {
        return array_sum(array_column($this->lineas, "cantidad"));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            "articulos" => array_map(static fn(array $l) => [
                "cantidad" => $l["cantidad"],
                "tarifa" => DatosBoleto::importe($l["tarifa"]),
                "recargo" => DatosBoleto::importe($l["recargo"]),
                "importe" => DatosBoleto::importe($l["importe"]),
            ], $this->lineas),
            "bultos" => $this->bultos(),
            "subtotal" => DatosBoleto::importe($this->subtotal),
            "porcentajeDescuento" => $this->porcentajeDescuento,
            "descuento" => DatosBoleto::importe($this->descuento),
            "total" => DatosBoleto::importe($this->total),
        ];
    }
}
