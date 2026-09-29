<?php

declare(strict_types=1);

namespace App\Venta;

/**
 * Elige la `BoletoTarifa` de un asiento (pura). Cada tarifa fija algunos de
 * estos atributos —los demás son comodín—: empresa, trayecto, hora, clase de
 * asiento y bus. Aplica la que coincide en todos los que fija y fija más;
 * si una fija un atributo con otro valor, no aplica. La clase es obligatoria.
 *
 * Empate en especificidad: gana la de id mayor (la más reciente), para que
 * dar de alta una tarifa nueva igual de específica la reemplace.
 */
final class EspecificidadTarifa
{
    /**
     * @param iterable<CandidatoTarifa> $candidatos
     */
    public static function elegir(
        iterable $candidatos,
        string $clase,
        ?int $empresaId,
        int $trayectoId,
        string $hora,
        ?int $busId,
    ): ?CandidatoTarifa {
        $mejor = null;
        $mejorPuntos = -1;

        foreach ($candidatos as $c) {
            if ($c->clase !== $clase) {
                continue;
            }
            $puntos = 1;
            foreach ([
                [$c->empresaId, $empresaId],
                [$c->trayectoId, $trayectoId],
                [$c->hora, $hora],
                [$c->busId, $busId],
            ] as [$fijado, $real]) {
                if ($fijado === null) {
                    continue;
                }
                if ($fijado !== $real) {
                    continue 2;
                }
                $puntos++;
            }

            if ($puntos > $mejorPuntos || ($puntos === $mejorPuntos && $c->id > $mejor->id)) {
                $mejor = $c;
                $mejorPuntos = $puntos;
            }
        }

        return $mejor;
    }
}
