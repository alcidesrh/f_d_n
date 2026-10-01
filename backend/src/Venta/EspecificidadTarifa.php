<?php

declare(strict_types=1);

namespace App\Venta;

/**
 * Elige la `BoletoTarifa` de un asiento (pura).
 *
 * Una tarifa aplica a un asiento de una salida si su trayecto es el que se
 * vende (obligatorio) y cada uno de los demás atributos —empresa, hora, bus y
 * clase de asiento— es igual al de la salida/asiento o es null (comodín). Un
 * subtrayecto se cotiza como una salida más: su trayecto, con la empresa y el
 * bus de la salida que lo contiene y la hora estimada en su parada de origen.
 * Si esa hora no se conoce se cotiza sin hora: solo aplican las tarifas que no
 * la fijan.
 *
 * Entre las que aplican gana la que fija los atributos de mayor rango, en
 * este orden: empresa > hora > bus > clase. Es un orden lexicográfico, no un
 * conteo: fijar la empresa pesa más que fijar hora, bus y clase juntos. Por
 * eso cada atributo vale el doble que la suma de los que le siguen.
 *
 * Empate (fijan exactamente los mismos atributos): gana la de id mayor (la
 * más reciente), para que dar de alta una tarifa nueva igual de específica la
 * reemplace.
 */
final class EspecificidadTarifa
{
    public const PESO_EMPRESA = 8;
    public const PESO_HORA = 4;
    public const PESO_BUS = 2;
    public const PESO_CLASE = 1;

    /**
     * @param iterable<CandidatoTarifa> $candidatos
     * @param ?string $hora `H:i` en el origen del trayecto; null si no se conoce
     */
    public static function elegir(
        iterable $candidatos,
        string $clase,
        int $trayectoId,
        ?int $empresaId,
        ?string $hora,
        ?int $busId,
    ): ?CandidatoTarifa {
        $mejor = null;
        $mejorPeso = -1;

        foreach ($candidatos as $c) {
            $peso = self::peso($c, $clase, $trayectoId, $empresaId, $hora, $busId);
            if ($peso === null) {
                continue;
            }
            if ($peso > $mejorPeso || ($peso === $mejorPeso && $c->id > $mejor->id)) {
                $mejor = $c;
                $mejorPeso = $peso;
            }
        }

        return $mejor;
    }

    /**
     * Prioridad de la tarifa para ese asiento; null si no aplica.
     */
    public static function peso(
        CandidatoTarifa $c,
        string $clase,
        int $trayectoId,
        ?int $empresaId,
        ?string $hora,
        ?int $busId,
    ): ?int {
        if ($c->trayectoId !== $trayectoId) {
            return null;
        }

        $peso = 0;
        foreach ([
            [$c->empresaId, $empresaId, self::PESO_EMPRESA],
            [$c->hora, $hora, self::PESO_HORA],
            [$c->busId, $busId, self::PESO_BUS],
            [$c->clase, $clase, self::PESO_CLASE],
        ] as [$fijado, $real, $valor]) {
            if ($fijado === null) {
                continue;
            }
            if ($fijado !== $real) {
                return null;
            }
            $peso += $valor;
        }

        return $peso;
    }
}
