<?php

declare(strict_types=1);

namespace App\Venta\Pago;

use Money\Money;

final readonly class SolicitudPago
{
    public function __construct(
        /** Referencia única del comercio (el token del carrito). */
        public string $referencia,
        public Money $monto,
        public Tarjeta $tarjeta,
        public string $correo,
        public string $descripcion,
        /** URL a la que el banco devuelve al cliente tras 3-D Secure. */
        public string $urlRetorno,
        public ?string $ip = null,
    ) {}
}
