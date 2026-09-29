<?php

declare(strict_types=1);

namespace App\Venta\Facturacion;

use Money\Money;

final readonly class ItemDte
{
    public function __construct(
        public string $descripcion,
        public int $cantidad,
        public Money $precioUnitario,
        public Money $total,
    ) {}
}
