<?php

declare(strict_types=1);

namespace App\Venta;

use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Entity\Enum\CanalVenta;
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
     * Una factura (certificada, o por certificar) cubre toda la venta y el
     * certificador no la anula por partes: hay que anular todos sus boletos
     * vivos juntos.
     *
     * @param list<BoletoAsiento> $seleccion los boletos que se quieren anular
     *
     * @throws VentaRechazada
     */
    public static function exigirVentaCompleta(BoletoVenta $venta, array $seleccion): void
    {
        if (!in_array($venta->getEstadoFacturacion(), [EstadoFacturacion::CERTIFICADA, EstadoFacturacion::PENDIENTE], true)) {
            return;
        }
        /** @var list<BoletoAsiento> $faltan */
        $faltan = array_values(array_filter(
            $venta->getAsientos()->toArray(),
            static fn(BoletoAsiento $b) => !in_array($b->getEstado(), [EstadoBoletoAsiento::ANULADO, EstadoBoletoAsiento::REASIGNADO], true)
                && !in_array($b, $seleccion, true),
        ));
        if ($faltan !== []) {
            throw new VentaRechazada(
                sprintf(
                    "La venta %d tiene una factura que cubre todos sus boletos: para anularla hay que anular también %s.",
                    $venta->getId(),
                    count($faltan) === 1 ? "el asiento " . $faltan[0]->getAsiento()?->getNumero() : "los asientos " . implode(", ", array_map(static fn(BoletoAsiento $b) => $b->getAsiento()?->getNumero(), $faltan)),
                ),
                "venta_incompleta",
                422,
                // Los boletos que faltan, para que la pantalla ofrezca incluirlos.
                ["venta" => $venta->getId(), "boletos" => array_map(static fn(BoletoAsiento $b) => $b->getId(), $faltan)],
            );
        }
    }

    /**
     * Entre empresas distintas solo se reasigna lo vendido en la página web.
     *
     * @throws VentaRechazada
     */
    public static function exigirEmpresaCompatible(Salida $origen, Salida $destino, BoletoVenta $venta): void
    {
        if ($venta->getCanal() === CanalVenta::WEB) {
            return;
        }
        if ($origen->getEmpresa()?->getId() !== $destino->getEmpresa()?->getId()) {
            throw new VentaRechazada(
                "La salida elegida es de otra empresa: solo los boletos vendidos en la página web se reasignan entre empresas.",
                "empresa_distinta",
            );
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
