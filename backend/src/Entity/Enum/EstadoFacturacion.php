<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Situación de la factura electrónica (DTE) de una venta.
 *
 * - `certificada`: el certificador emitió el DTE (`BoletoVenta.factura`).
 * - `pendiente`: debía certificarse y aún no se pudo (contingencia en
 *   taquilla o fallo tras cobrar en la web); `app:venta:certificar-pendientes` reintenta.
 * - `anulada`: se anularon todos los boletos de la venta (y su DTE, si lo
 *   tenía); ya no hay nada que certificar.
 * - `no_aplica`: agencias, cortesías y ventas migradas del legado.
 */
enum EstadoFacturacion: string
{
    case CERTIFICADA = "certificada";
    case PENDIENTE = "pendiente";
    case NO_APLICA = "no_aplica";
    case ANULADA = "anulada";
}
