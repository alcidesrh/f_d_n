<?php

namespace App\Entity\Enum;

/**
 * Entrega a los destinatarios de fuera (por el proveedor SMTP). A los de la
 * casa se entrega en el acto, sin proveedor: un enviado sin externos queda
 * `Enviado` al enviarse.
 */
enum EnvioCorreo: string
{
    case Pendiente = "pendiente";
    case Enviado = "enviado";
    case Fallido = "fallido";
}
