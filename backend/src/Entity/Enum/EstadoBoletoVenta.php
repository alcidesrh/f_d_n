<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Ciclo de vida de una venta (ADR-021):
 *
 * - `pendiente`: los asientos ya están apartados y la venta espera la
 *   certificación de la factura. Si la certificación falla la venta se borra
 *   ("no se hace nada"); si el proceso muere a mitad, el comando
 *   `app:venta:purgar` la borra al vencer.
 * - `confirmada`: venta firme (con factura, sin ella por contingencia, o sin
 *   ella porque no aplica).
 */
enum EstadoBoletoVenta: string
{
    case PENDIENTE = "pendiente";
    case CONFIRMADA = "confirmada";
}
