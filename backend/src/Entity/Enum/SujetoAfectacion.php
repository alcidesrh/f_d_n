<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Sobre qué cae una afectación de un tipo (ADR-070). Decide qué datos pide
 * al reportarla y dónde se ubica para el alcance: quién la puede reportar,
 * a quién se avisa y quién la ve.
 *
 * - `estacion`: equipos, red, insumos o instalaciones de una estación (y de
 *   una empresa: las estaciones son compartidas).
 * - `bus`: un bus de la flota; la empresa es la del bus.
 * - `empresa`: lo general de una empresa, sin estación (un servicio contratado).
 */
enum SujetoAfectacion: string
{
    case ESTACION = "estacion";
    case BUS = "bus";
    case EMPRESA = "empresa";

    public function etiqueta(): string
    {
        return match ($this) {
            self::ESTACION => "Estación",
            self::BUS => "Bus",
            self::EMPRESA => "Empresa",
        };
    }
}
