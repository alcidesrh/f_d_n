<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum EstadoPagoWeb: string
{
    /** Esperando que el cliente se autentique con su banco (3-D Secure). */
    case AUTENTICACION = "autenticacion";
    /** Cobrado; la venta se registra a continuación. */
    case APROBADO = "aprobado";
    case RECHAZADO = "rechazado";
    /** Cobrado y con la venta registrada. */
    case COMPLETADO = "completado";
    /** Cobrado pero la venta no pudo registrarse: se devolvió el dinero. */
    case REEMBOLSADO = "reembolsado";
}
