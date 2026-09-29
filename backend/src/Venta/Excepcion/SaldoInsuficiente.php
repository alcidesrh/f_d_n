<?php

declare(strict_types=1);

namespace App\Venta\Excepcion;

use Money\Money;

final class SaldoInsuficiente extends VentaRechazada
{
    public static function para(Money $total, int $saldo): self
    {
        return new self(
            sprintf(
                "Saldo de la agencia insuficiente: la venta suma %s %s y el saldo es %s. Solicite una recarga.",
                $total->getCurrency()->getCode(),
                number_format(((int) $total->getAmount()) / 100, 2),
                number_format($saldo / 100, 2),
            ),
            "saldo_insuficiente",
            409,
            ["saldo" => $saldo, "total" => (int) $total->getAmount()],
        );
    }
}
