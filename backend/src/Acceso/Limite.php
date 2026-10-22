<?php

declare(strict_types=1);

namespace App\Acceso;

/**
 * Límites cuantitativos que un rol fija a una de sus capacidades (ADR-039).
 * Conjunto cerrado: cada uno lo hace cumplir el servicio que opera. Sin valor
 * en el rol, no hay límite; si varias asignaciones conceden la capacidad,
 * rige la más permisiva. Pasarlo una vez se pide con una autorización (ADR-040).
 */
enum Limite: string
{
    case CORTESIAS_POR_DIA = 'cortesias_por_dia';
    case REASIGNACIONES_POR_DIA = 'reasignaciones_por_dia';
    case ANULACIONES_POR_DIA = 'anulaciones_por_dia';

    public function capacidad(): Capacidad
    {
        return match ($this) {
            self::CORTESIAS_POR_DIA => Capacidad::VENTA_CORTESIA,
            self::REASIGNACIONES_POR_DIA => Capacidad::BOLETO_REASIGNAR,
            self::ANULACIONES_POR_DIA => Capacidad::BOLETO_ANULAR,
        };
    }

    public function nombre(): string
    {
        return match ($this) {
            self::CORTESIAS_POR_DIA => 'Asientos de cortesía por día',
            self::REASIGNACIONES_POR_DIA => 'Boletos reasignados por día',
            self::ANULACIONES_POR_DIA => 'Boletos anulados por día',
        };
    }
}
