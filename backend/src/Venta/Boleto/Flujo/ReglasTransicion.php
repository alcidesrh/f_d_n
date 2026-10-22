<?php

declare(strict_types=1);

namespace App\Venta\Boleto\Flujo;

use App\Entity\BoletoAsiento;
use App\Entity\Enum\EstadoBoletoVenta;
use App\Entity\Enum\EstadoSalida;
use App\Entity\Enum\TransicionBoleto;
use App\Salida\Abordaje\ReglasAbordaje;
use App\Salida\SalidaRechazada;
use App\Venta\Excepcion\VentaRechazada;
use App\Venta\ReglasBoletos;

/**
 * Lo que cada transición del boleto exige además de su estado (que lo
 * asegura la máquina de estados): el estado de la salida, que la venta esté
 * confirmada, la hora. Son los guards del workflow (`GuardiaBoleto`, ADR-037)
 * y reúsan las reglas puras del abordaje y de la venta. Puras, sin base de
 * datos: quién la pide (permiso, estación) y lo que se consulta (que el
 * asiento siga libre al revertir, la salida destino al reasignar) lo
 * comprueba el servicio que opera.
 */
final class ReglasTransicion
{
    /** Las que opera una persona sobre una venta ya cerrada (el resto las causa la salida). */
    private const CON_VENTA_CONFIRMADA = [
        TransicionBoleto::CHEQUEAR,
        TransicionBoleto::CHEQUEAR_EN_RUTA,
        TransicionBoleto::CANCELAR,
        TransicionBoleto::ANULAR,
        TransicionBoleto::REASIGNAR,
        TransicionBoleto::POSPONER,
    ];

    /**
     * El motivo por el que no se puede, o null. `$fueraDeHora`: con una
     * autorización fuera de hora (ADR-040) anular o reasignar no mira la hora
     * de la salida, solo que no esté finalizada ni anulada.
     */
    public static function rechazo(TransicionBoleto $t, BoletoAsiento $b, \DateTimeImmutable $ahora, bool $fueraDeHora = false): SalidaRechazada|VentaRechazada|null
    {
        try {
            self::exigir($t, $b, $ahora, $fueraDeHora);
        } catch (SalidaRechazada|VentaRechazada $e) {
            return $e;
        }

        return null;
    }

    /** @throws SalidaRechazada|VentaRechazada */
    public static function exigir(TransicionBoleto $t, BoletoAsiento $b, \DateTimeImmutable $ahora, bool $fueraDeHora = false): void
    {
        $salida = $b->getSalida();
        $venta = $b->getBoletoVenta();
        if (in_array($t, self::CON_VENTA_CONFIRMADA, true) && $venta->getEstado() !== EstadoBoletoVenta::CONFIRMADA) {
            throw self::rechazar($t, sprintf("La venta del boleto %d todavía se está procesando.", $b->getId()), "venta_en_proceso", 409);
        }

        $estado = $salida->getEstado();
        match ($t) {
            TransicionBoleto::CHEQUEAR, TransicionBoleto::CHEQUEAR_EN_RUTA => self::chequear($t, $estado, $salida->getFecha(), $ahora),
            // Desde que la salida aborda: NO en el chequeo, al iniciar o al finalizar.
            TransicionBoleto::NO_PRESENTARSE => self::exigirSalida($estado, [EstadoSalida::ABORDANDO, EstadoSalida::INICIADA, EstadoSalida::FINALIZADA], "un pasajero no se presenta antes de que la salida empiece a abordar", "salida_no_abordando"),
            // Al iniciar; también al finalizar una salida migrada que no pasó por iniciar.
            TransicionBoleto::PARTIR => self::exigirSalida($estado, [EstadoSalida::INICIADA, EstadoSalida::FINALIZADA], "el pasajero parte cuando inicia la salida", "salida_no_iniciada"),
            TransicionBoleto::LLEGAR => self::exigirSalida($estado, [EstadoSalida::FINALIZADA], "el pasajero llega cuando finaliza la salida", "salida_no_finalizada"),
            TransicionBoleto::CANCELAR => ReglasAbordaje::exigirCancelable($b->getEstado(), $estado),
            TransicionBoleto::REVERTIR => ReglasAbordaje::exigirRevertible($b->getEstado(), $estado, $venta->isCortesia(), $venta->getAgencia() !== null),
            TransicionBoleto::ANULAR => $fueraDeHora ? ReglasBoletos::exigirOperableFueraDeHora($b, "anular el boleto") : ReglasBoletos::exigirOperable($b, $ahora, "anular el boleto"),
            TransicionBoleto::REASIGNAR => $fueraDeHora ? ReglasBoletos::exigirOperableFueraDeHora($b, "reasignar el boleto") : ReglasBoletos::exigirOperable($b, $ahora, "reasignar el boleto"),
            TransicionBoleto::POSPONER => ReglasBoletos::exigirOperable($b, $ahora, "posponer el viaje"),
        };
    }

    /** La excepción del lado que opera la transición (`TransicionBoleto::deLaVenta`). */
    public static function rechazar(TransicionBoleto $t, string $mensaje, string $codigo, int $estadoHttp): SalidaRechazada|VentaRechazada
    {
        return $t->deLaVenta() ? new VentaRechazada($mensaje, $codigo, $estadoHttp) : new SalidaRechazada($mensaje, $codigo, $estadoHttp);
    }

    /**
     * Con la salida abordando o iniciada y dentro de la ventana; cuál de
     * las dos lo dice la salida (`TransicionBoleto::alChequear`).
     *
     * @throws SalidaRechazada
     */
    private static function chequear(TransicionBoleto $t, EstadoSalida $salida, \DateTimeInterface $fecha, \DateTimeImmutable $ahora): void
    {
        ReglasAbordaje::exigirChequeable($salida, $fecha, $ahora);
        if (TransicionBoleto::alChequear($salida) !== $t) {
            throw new SalidaRechazada(
                $t === TransicionBoleto::CHEQUEAR ? "La salida ya inició: al chequearlo el boleto queda en tránsito." : "La salida todavía no inicia: al chequearlo el boleto queda chequeado.",
                "salida_no_coincide",
                409,
            );
        }
    }

    /**
     * @param list<EstadoSalida> $estados
     *
     * @throws SalidaRechazada
     */
    private static function exigirSalida(EstadoSalida $estado, array $estados, string $regla, string $codigo): void
    {
        if (!in_array($estado, $estados, true)) {
            throw new SalidaRechazada(sprintf("La salida está %s: %s.", $estado->value, $regla), $codigo, 409);
        }
    }
}
