<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Por dónde entró una venta de boletos (`BoletoVenta.canal`):
 *
 * - `estacion`: taquilla, un usuario de la empresa atiende al cliente.
 * - `agencia`: usuario de una agencia (vende por comisión, descuenta saldo, sin factura electrónica).
 * - `web`: el propio cliente en la página pública (pago con tarjeta y 3-D Secure).
 */
enum CanalVenta: string
{
    case ESTACION = "estacion";
    case AGENCIA = "agencia";
    case WEB = "web";
}
