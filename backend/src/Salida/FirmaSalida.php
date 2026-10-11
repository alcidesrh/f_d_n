<?php

declare(strict_types=1);

namespace App\Salida;

use App\Entity\Salida;

/**
 * Lo que hace "idénticas" a dos salidas para propagar un cambio (ADR-024):
 * misma ruta, bus, empresa y hora del día; solo cambia el día. Puro.
 */
final class FirmaSalida
{
    public static function de(?int $rutaId, ?int $busId, ?int $empresaId, \DateTimeInterface $fecha): string
    {
        return sprintf("%d|%d|%d|%s", $rutaId ?? 0, $busId ?? 0, $empresaId ?? 0, $fecha->format("H:i"));
    }

    public static function deSalida(Salida $s): string
    {
        return self::de($s->getRuta()?->getId(), $s->getBus()?->getId(), $s->getEmpresa()?->getId(), $s->getFecha());
    }

    /** La fecha `$dia` con la hora y minutos de `$hora`. */
    public static function conHora(\DateTimeInterface $dia, \DateTimeInterface $hora): \DateTime
    {
        return \DateTime::createFromInterface($dia)->setTime((int) $hora->format("H"), (int) $hora->format("i"));
    }
}
