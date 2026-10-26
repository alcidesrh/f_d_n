<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Entity\Enum\EstadoBoletoAsiento;
use App\Entity\Enum\EstadoFacturacion;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Salida;
use App\Venta\Boleto\DatosBoleto;
use App\Venta\Excepcion\VentaRechazada;
use Money\Money;

/**
 * Reglas puras de anular y reasignar boletos (sin base de datos): lo que
 * `AnulacionBoletos` y `ReasignacionBoletos` exigen antes de tocar nada.
 */
final class ReglasBoletos
{
    /**
     * Solo antes de la hora de salida y mientras la salida siga programada o
     * abordando.
     *
     * @throws VentaRechazada
     */
    public static function exigirAntesDeSalir(Salida $salida, \DateTimeInterface $ahora, string $operacion): void
    {
        if (!in_array($salida->getEstado(), [EstadoSalida::PROGRAMADA, EstadoSalida::ABORDANDO], true)) {
            throw new VentaRechazada(
                sprintf("La salida está %s: ya no se puede %s.", $salida->getEstado()->value, $operacion),
                "salida_no_operable",
            );
        }
        if ($salida->getFecha() === null || $salida->getFecha() <= $ahora) {
            throw new VentaRechazada(
                sprintf("La salida de las %s ya empezó: solo se puede %s antes de la hora de salida.", $salida->getFecha()?->format("d/m/Y H:i"), $operacion),
                "salida_iniciada",
            );
        }
    }

    /** @throws VentaRechazada */
    public static function exigirEmitido(BoletoAsiento $boleto): void
    {
        if ($boleto->getEstado() !== EstadoBoletoAsiento::EMITIDO) {
            throw new VentaRechazada(
                sprintf("El boleto del asiento %s está %s: solo se opera sobre boletos emitidos.", $boleto->getAsiento()?->getNumero(), $boleto->getEstado()->value),
                "boleto_no_emitido",
            );
        }
    }

    /**
     * Lo que se anula o se reasigna: un boleto emitido antes de la hora de
     * salida, o uno pospuesto (no ocupa asiento: su salida ya no importa).
     *
     * @throws VentaRechazada
     */
    public static function exigirOperable(BoletoAsiento $boleto, \DateTimeInterface $ahora, string $operacion): void
    {
        if ($boleto->getEstado() === EstadoBoletoAsiento::POSPUESTO) {
            return;
        }
        self::exigirEmitido($boleto);
        self::exigirAntesDeSalir($boleto->getSalida(), $ahora, $operacion);
    }

    /**
     * Lo que permite una autorización fuera de hora (ADR-040): un boleto
     * emitido (o pospuesto) aunque su salida ya haya pasado su hora o
     * iniciado, mientras no se haya finalizado ni anulado.
     *
     * @throws VentaRechazada
     */
    public static function exigirOperableFueraDeHora(BoletoAsiento $boleto, string $operacion): void
    {
        if ($boleto->getEstado() === EstadoBoletoAsiento::POSPUESTO) {
            return;
        }
        self::exigirEmitido($boleto);
        $salida = $boleto->getSalida();
        if (!in_array($salida->getEstado(), [EstadoSalida::PROGRAMADA, EstadoSalida::ABORDANDO, EstadoSalida::INICIADA], true)) {
            throw new VentaRechazada(
                sprintf("La salida está %s: ya no se puede %s, ni con autorización.", $salida->getEstado()->value, $operacion),
                "salida_no_operable",
            );
        }
    }

    /** ¿Solo la hora impide anularlo o reasignarlo? Entonces se puede con una autorización fuera de hora. */
    public static function soloPorHora(BoletoAsiento $boleto, \DateTimeInterface $ahora): bool
    {
        try {
            self::exigirOperable($boleto, $ahora, "operarlo");

            return false;
        } catch (VentaRechazada) {
        }
        try {
            self::exigirOperableFueraDeHora($boleto, "operarlo");

            return true;
        } catch (VentaRechazada) {
            return false;
        }
    }

    /**
     * Boletos vivos que cubre la factura de la venta (certificada o por
     * certificar); vacío si no tiene. Con más de uno es una factura conjunta:
     * anular parte de ellos anula la factura y certifica otra con los que
     * quedan (`AnulacionBoletos`).
     *
     * @return list<BoletoAsiento>
     */
    public static function boletosDeLaFactura(BoletoVenta $venta): array
    {
        if (!in_array($venta->getEstadoFacturacion(), [EstadoFacturacion::CERTIFICADA, EstadoFacturacion::PENDIENTE], true)) {
            return [];
        }

        return array_values(array_filter(
            $venta->getAsientos()->toArray(),
            static fn(BoletoAsiento $b) => !in_array($b->getEstado(), [EstadoBoletoAsiento::ANULADO, EstadoBoletoAsiento::REASIGNADO], true),
        ));
    }

    /**
     * Lo que se factura de la venta: su total menos lo anulado (el reasignado
     * tiene su reemplazo en la misma venta, al mismo precio).
     */
    public static function totalFacturable(BoletoVenta $venta): Money
    {
        $total = $venta->getTotal();
        foreach ($venta->getAsientos() as $b) {
            if ($b->getEstado() === EstadoBoletoAsiento::ANULADO && $b->getPrecio() !== null) {
                $total = $total->subtract($b->getPrecio());
            }
        }

        return $total;
    }

    /**
     * Solo se reasigna entre salidas de la misma empresa (también lo vendido
     * en la página web).
     *
     * @throws VentaRechazada
     */
    public static function exigirEmpresaCompatible(Salida $origen, Salida $destino): void
    {
        if ($origen->getEmpresa()?->getId() !== $destino->getEmpresa()?->getId()) {
            throw new VentaRechazada("La salida elegida es de otra empresa: solo se reasigna entre salidas de la misma empresa.", "empresa_distinta");
        }
    }

    /** @throws VentaRechazada */
    public static function exigirMismoPrecio(BoletoAsiento $boleto, Money $nuevo, int|string $asiento): void
    {
        $anterior = $boleto->getPrecio();
        if ($anterior === null || !$anterior->equals($nuevo)) {
            throw new VentaRechazada(
                sprintf(
                    "El asiento %s cuesta %s y el boleto original %s: la reasignación exige el mismo precio.",
                    $asiento,
                    DatosBoleto::importe($nuevo)["texto"],
                    $anterior === null ? "no tiene precio" : DatosBoleto::importe($anterior)["texto"] . "",
                ),
                "precio_distinto",
            );
        }
    }
}
