<?php

declare(strict_types=1);

namespace App\Venta\Agencia;

use App\Entity\Agencia;
use App\Entity\AgenciaMovimiento;
use App\Entity\BoletoVenta;
use App\Entity\Enum\TipoMovimientoAgencia;
use App\Entity\Usuario;
use App\Venta\Excepcion\SaldoInsuficiente;
use App\Venta\Excepcion\VentaRechazada;
use Doctrine\DBAL\LockMode;
use App\Venta\Transaccion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Money\Money;

/**
 * Único punto que mueve el saldo de una agencia. Cada cambio bloquea la fila
 * de la agencia (dos ventas simultáneas no pueden gastar el mismo saldo) y
 * deja un `AgenciaMovimiento`. Debe llamarse dentro de una transacción.
 */
final class SaldoAgencia
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Transaccion $transaccion,
        private readonly EventDispatcherInterface $eventos,
    ) {}

    /**
     * Descuenta el total de una venta.
     *
     * @throws SaldoInsuficiente
     */
    public function debitarVenta(Agencia $agencia, BoletoVenta $venta, Usuario $usuario): void
    {
        $this->bloquear($agencia);
        $total = $venta->getTotal();
        $this->exigirMoneda($agencia, $total);
        $monto = (int) $total->getAmount();
        if ($monto === 0) {
            return;
        }
        if ($agencia->getSaldo() < $monto) {
            throw SaldoInsuficiente::para($total, $agencia->getSaldo());
        }

        $this->mover($agencia, TipoMovimientoAgencia::VENTA, -$monto, $usuario, $venta);
    }

    /**
     * Devuelve el precio de boletos anulados (`$centavos` > 0).
     */
    public function reintegrarAnulacion(Agencia $agencia, int $centavos, \Money\Currency $moneda, BoletoVenta $venta, Usuario $usuario, string $observacion): void
    {
        $this->bloquear($agencia);
        $this->exigirMoneda($agencia, new Money(0, $moneda));
        if ($centavos <= 0) {
            return;
        }

        $this->mover($agencia, TipoMovimientoAgencia::ANULACION, $centavos, $usuario, $venta, null, mb_substr($observacion, 0, 255));
    }

    /**
     * Registra un depósito y, si corresponde, su bonificación
     * (`porcentajeBonificacion` sobre el importe).
     *
     * @return list<AgenciaMovimiento>
     */
    public function depositar(
        Agencia $agencia,
        int $centavos,
        Usuario $usuario,
        ?string $referencia,
        ?string $observacion,
        bool $aplicarBonificacion,
    ): array {
        if ($centavos <= 0) {
            throw new VentaRechazada("El importe del depósito debe ser mayor que cero.");
        }
        $this->bloquear($agencia);

        $movimientos = [
            $this->mover($agencia, TipoMovimientoAgencia::DEPOSITO, $centavos, $usuario, null, $referencia, $observacion),
        ];
        $bono = $aplicarBonificacion ? self::bonificacion($centavos, $agencia->getPorcentajeBonificacion()) : 0;
        if ($bono > 0) {
            $movimientos[] = $this->mover(
                $agencia,
                TipoMovimientoAgencia::BONIFICACION,
                $bono,
                $usuario,
                null,
                $referencia,
                sprintf("%s%% sobre el depósito", $agencia->getPorcentajeBonificacion()),
            );
        }

        $this->avisar($agencia, SaldoAcreditado::DEPOSITO, $centavos, $bono, $referencia, $observacion);

        return $movimientos;
    }

    /** Corrección manual con signo. */
    public function ajustar(Agencia $agencia, int $centavos, Usuario $usuario, string $observacion): AgenciaMovimiento
    {
        if ($centavos === 0) {
            throw new VentaRechazada("El ajuste no puede ser cero.");
        }
        $this->bloquear($agencia);
        if ($agencia->getSaldo() + $centavos < 0) {
            throw new VentaRechazada("El ajuste dejaría el saldo de la agencia en negativo.");
        }

        $movimiento = $this->mover($agencia, TipoMovimientoAgencia::AJUSTE, $centavos, $usuario, null, null, $observacion);
        $this->avisar($agencia, SaldoAcreditado::AJUSTE, $centavos, 0, null, $observacion);

        return $movimiento;
    }

    /** El aviso sale solo si la operación se confirma. */
    private function avisar(Agencia $agencia, string $tipo, int $importe, int $bono, ?string $referencia, ?string $observacion): void
    {
        $evento = new SaldoAcreditado((int) $agencia->getId(), $tipo, $importe, $bono, $agencia->getSaldo(), $agencia->getMoneda(), $referencia, $observacion);
        $this->transaccion->despuesDeConfirmar(fn() => $this->eventos->dispatch($evento));
    }

    /** Bonificación en centavos (redondeo hacia abajo: nunca se regala de más). */
    public static function bonificacion(int $centavos, ?string $porcentaje): int
    {
        if ($porcentaje === null || (float) $porcentaje <= 0) {
            return 0;
        }

        return intdiv($centavos * (int) round(((float) $porcentaje) * 100), 10000);
    }

    private function mover(
        Agencia $agencia,
        TipoMovimientoAgencia $tipo,
        int $centavos,
        ?Usuario $usuario,
        ?BoletoVenta $venta = null,
        ?string $referencia = null,
        ?string $observacion = null,
    ): AgenciaMovimiento {
        $agencia->aplicarMovimiento($centavos);
        $movimiento = new AgenciaMovimiento(
            $agencia,
            $tipo,
            $centavos,
            $agencia->getSaldo(),
            $usuario,
            $venta,
            $referencia,
            $observacion,
        );
        $this->em->persist($movimiento);

        return $movimiento;
    }

    private function bloquear(Agencia $agencia): void
    {
        $this->em->refresh($agencia, LockMode::PESSIMISTIC_WRITE);
    }

    private function exigirMoneda(Agencia $agencia, Money $total): void
    {
        if ($total->getCurrency()->getCode() !== $agencia->getMoneda()) {
            throw new VentaRechazada(sprintf(
                "La agencia opera en %s y la venta está en %s.",
                $agencia->getMoneda(),
                $total->getCurrency()->getCode(),
            ));
        }
    }
}
