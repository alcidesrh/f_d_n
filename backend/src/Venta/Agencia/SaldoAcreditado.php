<?php

declare(strict_types=1);

namespace App\Venta\Agencia;

/**
 * Cambió el saldo de una agencia por un depósito o un ajuste (ya confirmado
 * en la base de datos). Importes en centavos con signo.
 */
final class SaldoAcreditado
{
    public const DEPOSITO = "deposito";
    public const AJUSTE = "ajuste";

    public function __construct(
        public readonly int $agenciaId,
        public readonly string $tipo,
        public readonly int $importe,
        public readonly int $bonificacion,
        public readonly int $saldo,
        public readonly string $moneda,
        public readonly ?string $referencia = null,
        public readonly ?string $observacion = null,
    ) {}
}
