<?php

declare(strict_types=1);

namespace App\Venta\Pago;

use Money\Money;

final readonly class SolicitudPago
{
    public function __construct(
        /** Referencia única del comercio (el token del carrito). */
        public string $referencia,
        /** Empresa que cobra (cada una tiene su comercio en la pasarela). */
        public int $empresaId,
        public Money $monto,
        public Tarjeta $tarjeta,
        public DireccionFacturacion $direccion,
        public string $nombre,
        public ?string $apellido,
        public string $correo,
        public ?string $telefono,
        public string $descripcion,
        /** URL a la que el banco devuelve al cliente tras 3-D Secure. */
        public string $urlRetorno,
        public Navegador $navegador = new Navegador(),
    ) {}
}
