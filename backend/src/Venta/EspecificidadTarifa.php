<?php

declare(strict_types=1);

namespace App\Venta;

/**
 * Elige la `BoletoTarifa` de un asiento (pura).
 *
 * Siempre se exigen la clase de asiento, el trayecto (o tarifa sin trayecto)
 * y la vigencia (`vigenteDesde` ≤ ahora). Los demás campos, de mayor a menor
 * prioridad, son empresa, clase de bus, horario y bus: cada uno debe coincidir
 * o estar vacío en la tarifa. De las que cumplen gana la más reciente
 * (vigencia y, a igual vigencia, id mayor).
 *
 * Si ninguna cumple se deja de exigir el campo de menor prioridad (bus),
 * luego el horario, luego la clase de bus y por último la empresa, hasta que
 * haya candidatas. Así un bus de una clase sin tarifa propia en el trayecto
 * toma la más reciente del trayecto antes que quedarse sin precio.
 */
final class EspecificidadTarifa
{
    /** Campos que se pueden dejar de exigir, de mayor a menor prioridad. */
    private const PRIORIDAD = ["empresa", "busClase", "horario", "bus"];

    /**
     * @param iterable<CandidatoTarifa> $candidatos
     * @param string $hora `H:i` en que parte la salida
     */
    public static function elegir(
        iterable $candidatos,
        string $clase,
        ?int $empresaId,
        int $trayectoId,
        string $hora,
        ?int $busClaseId,
        ?int $busId,
        \DateTimeInterface $ahora,
    ): ?CandidatoTarifa {
        $base = [];
        foreach ($candidatos as $c) {
            if ($c->clase === $clase
                && $c->vigenteDesde <= $ahora
                && ($c->trayectoId === null || $c->trayectoId === $trayectoId)) {
                $base[] = $c;
            }
        }

        $cumple = static fn(CandidatoTarifa $c, string $campo): bool => match ($campo) {
            "empresa" => $c->empresaId === null || $c->empresaId === $empresaId,
            "busClase" => $c->busClaseId === null || $c->busClaseId === $busClaseId,
            "horario" => $c->enHorario($hora),
            "bus" => $c->busId === null || $c->busId === $busId,
        };

        for ($exigidos = count(self::PRIORIDAD); $exigidos >= 0; $exigidos--) {
            $campos = array_slice(self::PRIORIDAD, 0, $exigidos);
            $mejor = null;
            foreach ($base as $c) {
                foreach ($campos as $campo) {
                    if (!$cumple($c, $campo)) {
                        continue 2;
                    }
                }
                if ($mejor === null || self::masReciente($c, $mejor)) {
                    $mejor = $c;
                }
            }
            if ($mejor !== null) {
                return $mejor;
            }
        }

        return null;
    }

    private static function masReciente(CandidatoTarifa $c, CandidatoTarifa $otra): bool
    {
        return $c->vigenteDesde == $otra->vigenteDesde
            ? $c->id > $otra->id
            : $c->vigenteDesde > $otra->vigenteDesde;
    }
}
