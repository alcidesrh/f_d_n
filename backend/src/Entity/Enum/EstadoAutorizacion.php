<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Ciclo de una autorización (ADR-040): `pendiente → aprobada → usada`, o
 * `pendiente → rechazada | retirada`. Una pendiente o aprobada que pasa de su
 * `venceEn` está `vencida` (se calcula al leerla: `SolicitudAutorizacion::estadoEn`).
 */
enum EstadoAutorizacion: string
{
    case PENDIENTE = "pendiente";
    case APROBADA = "aprobada";
    case RECHAZADA = "rechazada";
    case USADA = "usada";
    case RETIRADA = "retirada";
    case VENCIDA = "vencida";

    public function etiqueta(): string
    {
        return match ($this) {
            self::PENDIENTE => "Pendiente",
            self::APROBADA => "Aprobada",
            self::RECHAZADA => "Rechazada",
            self::USADA => "Usada",
            self::RETIRADA => "Retirada",
            self::VENCIDA => "Vencida",
        };
    }

    /** Todavía puede cambiar (y vencer). */
    public function abierta(): bool
    {
        return $this === self::PENDIENTE || $this === self::APROBADA;
    }
}
