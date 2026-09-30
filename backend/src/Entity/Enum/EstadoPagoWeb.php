<?php

declare(strict_types=1);

namespace App\Entity\Enum;

enum EstadoPagoWeb: string
{
    /** Esperando un paso del navegador: datos del dispositivo o desafío 3-D Secure. */
    case AUTENTICACION = "autenticacion";
    /** Cobrado; la venta se registra a continuación. */
    case APROBADO = "aprobado";
    case RECHAZADO = "rechazado";
    /** Cobrado y con la venta registrada. */
    case COMPLETADO = "completado";
    /** Cobrado pero la venta no pudo registrarse: se devolvió el dinero. */
    case REEMBOLSADO = "reembolsado";
    /** Cobrado sin venta y la pasarela no aceptó el reembolso: devolverlo a mano. */
    case REEMBOLSO_PENDIENTE = "reembolso_pendiente";
}
