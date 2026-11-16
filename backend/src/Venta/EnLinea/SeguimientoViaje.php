<?php

declare(strict_types=1);

namespace App\Venta\EnLinea;

use App\Entity\BoletoAsiento;
use App\Entity\BoletoVenta;
use App\Seguimiento\HorasEstimadas;
use App\Venta\Boleto\DatosBoleto;
use App\Venta\EnLinea\Modificacion\MisCompras;

/**
 * «¿Dónde va mi bus?» (ADR-065): lo que el cliente ve de su viaje en «Mis
 * compras» mientras se acerca o va en camino: a qué hora se estima que el
 * bus pasa por donde sube y llega a donde baja, cuánto atraso lleva y dónde
 * está. Solo su tramo: las paradas de antes y de después no se muestran.
 */
final class SeguimientoViaje
{
    /** Desde cuánto antes de la hora de subida se muestra el seguimiento. */
    public const DESDE_HORAS = 3;
    /** Hasta cuánto después de la hora de llegada del horario. */
    public const HASTA_HORAS = 6;

    public function __construct(
        private readonly HorasEstimadas $llegadas,
    ) {}

    /**
     * Un tramo por cada salida y trayecto de la venta (una compra modificada
     * puede tener asientos en varias salidas).
     *
     * @return array{generado: int, tramos: list<array<string, mixed>>}
     */
    public function deVenta(BoletoVenta $venta, \DateTimeImmutable $ahora): array
    {
        $tramos = [];
        foreach (DatosBoleto::viajes(MisCompras::vivos($venta)) as $boletos) {
            /** @var BoletoAsiento $primero */
            $primero = $boletos[0];
            $salida = $primero->getSalida();
            $trayecto = $primero->getTrayecto();
            $estimacion = $this->llegadas->deSalida($salida, $ahora);
            $paradas = self::tramo($estimacion["paradas"], (int) $trayecto->getOrigen()->getId(), (int) $trayecto->getDestino()->getId());
            $visible = self::visible($paradas, $ahora->getTimestamp());

            $tramos[] = [
                "salida" => (int) $salida->getId(),
                "estado" => $salida->getEstado()->value,
                "bus" => $salida->getBus()?->getCodigo(),
                "asientos" => array_map(static fn(BoletoAsiento $b) => $b->getAsiento()->getNumero(), $boletos),
                "visible" => $visible,
                "fuente" => $visible ? $estimacion["fuente"] : "horario",
                "atrasoMinutos" => $visible ? $estimacion["atrasoMinutos"] : null,
                "lectura" => $visible ? $estimacion["lectura"] : null,
                "posicion" => $visible ? $estimacion["posicion"] : null,
                "paradas" => $paradas,
            ];
        }

        return ["generado" => $ahora->getTimestamp(), "tramos" => $tramos];
    }

    /**
     * Las paradas del viaje del cliente: de donde sube a donde baja. Si
     * alguna no está en el horario, ninguna.
     *
     * @param list<array{enclave: int, nombre: string, programada: ?int, estimada: ?int, paso: bool}> $paradas
     *
     * @return list<array{enclave: int, nombre: string, programada: ?int, estimada: ?int, paso: bool}>
     */
    public static function tramo(array $paradas, int $sube, int $baja): array
    {
        $ids = array_column($paradas, "enclave");
        $desde = array_search($sube, $ids, true);
        $hasta = array_search($baja, $ids, true);

        return $desde === false || $hasta === false || $desde >= $hasta ? [] : array_slice($paradas, $desde, $hasta - $desde + 1);
    }

    /**
     * Si el seguimiento se muestra ahora: desde unas horas antes de la hora
     * de subida hasta unas horas después de la de llegada (según el horario).
     *
     * @param list<array{programada: ?int}> $tramo
     */
    public static function visible(array $tramo, int $ahora): bool
    {
        $sube = $tramo[0]["programada"] ?? null;
        $baja = $tramo === [] ? null : $tramo[count($tramo) - 1]["programada"];
        if ($sube === null) {
            return false;
        }

        return $ahora >= $sube - self::DESDE_HORAS * 3600 && $ahora <= ($baja ?? $sube) + self::HASTA_HORAS * 3600;
    }
}
