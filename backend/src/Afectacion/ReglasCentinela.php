<?php

declare(strict_types=1);

namespace App\Afectacion;

use App\Entity\Afectacion;
use App\Entity\Enum\EstadoAfectacion;

/**
 * El protocolo del centinela (ADR-070), puro. Corre cada minuto sobre cada
 * afectación abierta y decide qué toca:
 *
 * 1. Recién creada (`creada`, sin aviso): el aviso inicial, solo a quien
 *    tiene el `target` del tipo donde cae.
 * 2. Pendiente: nada hasta que pasan `escalarTrasMinutos` desde que se creó.
 * 3. Pasado ese tiempo sin resolver: escalamiento, a `target` y además a las
 *    capacidades de `escalamiento` del tipo.
 * 4. Ya escalada: el mismo aviso, a los mismos, cada `intervaloMinutos`
 *    desde el último, hasta que se resuelve.
 *
 * Una creada que ya pasó el tiempo de escalar (el aviso inicial no encontró
 * a nadie, o el centinela estuvo detenido) recibe directamente el escalamiento.
 */
final class ReglasCentinela
{
    public const NADA = "nada";
    public const INICIAL = "inicial";
    public const ESCALAMIENTO = "escalamiento";

    public static function decidir(
        EstadoAfectacion $estado,
        \DateTimeImmutable $creadaEn,
        ?\DateTimeImmutable $escaladaEn,
        ?\DateTimeImmutable $ultimoAvisoEn,
        int $intervaloMinutos,
        int $escalarTrasMinutos,
        \DateTimeImmutable $ahora,
    ): string {
        if (!$estado->abierta()) {
            return self::NADA;
        }
        if ($escaladaEn === null) {
            if (self::pasaron($creadaEn, $ahora, $escalarTrasMinutos)) {
                return self::ESCALAMIENTO;
            }

            return $estado === EstadoAfectacion::CREADA ? self::INICIAL : self::NADA;
        }

        return self::pasaron($ultimoAvisoEn ?? $escaladaEn, $ahora, max(1, $intervaloMinutos)) ? self::ESCALAMIENTO : self::NADA;
    }

    public static function para(Afectacion $a, \DateTimeImmutable $ahora): string
    {
        $t = $a->getTipo();

        return self::decidir($a->getEstado(), $a->getCreadaEn(), $a->getEscaladaEn(), $a->getUltimoAvisoEn(), $t->getIntervaloMinutos(), $t->getEscalarTrasMinutos(), $ahora);
    }

    /**
     * Cuándo vuelve a avisar el centinela si nadie la resuelve: el
     * escalamiento si todavía no escaló, o la próxima repetición. Null si
     * está resuelta; una creada sin aviso inicial se avisa ya.
     */
    public static function proximoAviso(
        EstadoAfectacion $estado,
        \DateTimeImmutable $creadaEn,
        ?\DateTimeImmutable $escaladaEn,
        ?\DateTimeImmutable $ultimoAvisoEn,
        int $intervaloMinutos,
        int $escalarTrasMinutos,
        \DateTimeImmutable $ahora,
    ): ?\DateTimeImmutable {
        if (!$estado->abierta()) {
            return null;
        }
        $proximo = $escaladaEn === null
            ? ($estado === EstadoAfectacion::CREADA ? $ahora : $creadaEn->modify("+{$escalarTrasMinutos} minutes"))
            : ($ultimoAvisoEn ?? $escaladaEn)->modify("+" . max(1, $intervaloMinutos) . " minutes");

        return $proximo < $ahora ? $ahora : $proximo;
    }

    /** Pasaron al menos `$minutos` de `$desde` a `$ahora`. */
    private static function pasaron(\DateTimeImmutable $desde, \DateTimeImmutable $ahora, int $minutos): bool
    {
        return $ahora->getTimestamp() - $desde->getTimestamp() >= $minutos * 60;
    }
}
