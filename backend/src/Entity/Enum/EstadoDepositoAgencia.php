<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Estado de un `AgenciaDeposito` (ADR-034). Solo `registrado` cambia:
 * pasa a `acreditado` (mueve el saldo) o a `rechazado` (con motivo).
 * En el legado, `agencia_deposito_estado` 1, 2 y 3.
 */
enum EstadoDepositoAgencia: string
{
    case REGISTRADO = "registrado";
    case ACREDITADO = "acreditado";
    case RECHAZADO = "rechazado";

    public static function deLegado(int $id): self
    {
        return match ($id) {
            2 => self::ACREDITADO,
            3 => self::RECHAZADO,
            default => self::REGISTRADO,
        };
    }
}
