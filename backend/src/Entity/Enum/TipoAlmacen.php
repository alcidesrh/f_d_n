<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Qué clase de lugar es un almacén (ADR-071). No hay bodega central: cada
 * estación, taller, oficina y bus guarda lo suyo, y lo que no se consigue en
 * el lugar se envía desde la capital (un almacén más, de los de allá).
 */
enum TipoAlmacen: string
{
    case ESTACION = "estacion";
    case TALLER = "taller";
    case OFICINA = "oficina";
    case BUS = "bus";

    public function etiqueta(): string
    {
        return match ($this) {
            self::ESTACION => "Estación",
            self::TALLER => "Taller",
            self::OFICINA => "Oficina",
            self::BUS => "Bus",
        };
    }

    /** El tipo de afectación que crea su faltante si el artículo no dice otro. */
    public function tipoAfectacionFaltante(): string
    {
        return match ($this) {
            self::TALLER, self::BUS => "repuesto_faltante",
            self::ESTACION, self::OFICINA => "insumo_agotado",
        };
    }
}
