<?php

declare(strict_types=1);

namespace App\Venta\Agencia;

/** Se rechazó un depósito de agencia (ya confirmado). Importe en centavos. */
final class DepositoRechazado
{
    public function __construct(
        public readonly int $agenciaId,
        public readonly string $numeroBoleta,
        public readonly int $importe,
        public readonly string $moneda,
        public readonly string $motivo,
    ) {}
}
