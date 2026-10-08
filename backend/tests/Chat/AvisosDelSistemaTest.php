<?php

declare(strict_types=1);

namespace App\Tests\Chat;

use App\Chat\AvisosDelSistema;
use App\Venta\Agencia\SaldoAcreditado;
use PHPUnit\Framework\TestCase;

final class AvisosDelSistemaTest extends TestCase
{
    public function testDepositoConBoletaYBonificacion(): void
    {
        $texto = AvisosDelSistema::textoSaldo(new SaldoAcreditado(1, SaldoAcreditado::DEPOSITO, 50000, 2500, 252500, "GTQ", "123"));

        $this->assertSame("Se acreditó su depósito de Q 500.00 (boleta 123) y una bonificación de Q 25.00. Saldo disponible: Q 2,525.00.", $texto);
    }

    public function testAjusteNegativoConMotivo(): void
    {
        $texto = AvisosDelSistema::textoSaldo(new SaldoAcreditado(1, SaldoAcreditado::AJUSTE, -1000, 0, 9000, "GTQ", null, "Boleto duplicado"));

        $this->assertSame("Se ajustó su saldo en −Q 10.00: Boleto duplicado. Saldo disponible: Q 90.00.", $texto);
    }
}
