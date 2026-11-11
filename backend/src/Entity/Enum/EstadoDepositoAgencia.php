<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Estado de un `AgenciaDeposito` (ADR-034). Solo `registrado` cambia:
 * pasa a `acreditado` (mueve el saldo) o a `rechazado` (con motivo).
 */
enum EstadoDepositoAgencia: string
{
    case REGISTRADO = "registrado";
    case ACREDITADO = "acreditado";
    case RECHAZADO = "rechazado";
}
