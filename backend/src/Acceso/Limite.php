<?php

declare(strict_types=1);

namespace App\Acceso;

/**
 * Límites cuantitativos que un rol fija a una de sus capacidades (ADR-039).
 * Conjunto cerrado: cada uno lo hace cumplir el servicio que opera. Sin valor
 * en el rol, no hay límite; si varias asignaciones conceden la capacidad,
 * rige la más permisiva.
 *
 * Dos clases (ADR-044):
 * - Los que bloquean (`bloquea()`): pasarlo una vez se pide con una
 *   autorización (ADR-040).
 * - Los de aviso: no detienen a nadie; al llegar a ese número en el día, se
 *   avisa por el chat a quien aprueba autorizaciones donde ocurrió
 *   (`App\Vigilancia\UmbralesDiarios`). Sirven para mirar de cerca a quien
 *   no tiene límite o para enterarse antes de que llegue al suyo.
 */
enum Limite: string
{
    case CORTESIAS_POR_DIA = 'cortesias_por_dia';
    case REASIGNACIONES_POR_DIA = 'reasignaciones_por_dia';
    case ANULACIONES_POR_DIA = 'anulaciones_por_dia';
    case AVISO_CORTESIAS_POR_DIA = 'aviso_cortesias_por_dia';
    case AVISO_REASIGNACIONES_POR_DIA = 'aviso_reasignaciones_por_dia';
    case AVISO_ANULACIONES_POR_DIA = 'aviso_anulaciones_por_dia';

    public function capacidad(): Capacidad
    {
        return match ($this) {
            self::CORTESIAS_POR_DIA, self::AVISO_CORTESIAS_POR_DIA => Capacidad::VENTA_CORTESIA,
            self::REASIGNACIONES_POR_DIA, self::AVISO_REASIGNACIONES_POR_DIA => Capacidad::BOLETO_REASIGNAR,
            self::ANULACIONES_POR_DIA, self::AVISO_ANULACIONES_POR_DIA => Capacidad::BOLETO_ANULAR,
        };
    }

    public function nombre(): string
    {
        return match ($this) {
            self::CORTESIAS_POR_DIA => 'Asientos de cortesía por día',
            self::REASIGNACIONES_POR_DIA => 'Boletos reasignados por día',
            self::ANULACIONES_POR_DIA => 'Boletos anulados por día',
            self::AVISO_CORTESIAS_POR_DIA => 'Avisar a quien aprueba desde N asientos de cortesía en el día',
            self::AVISO_REASIGNACIONES_POR_DIA => 'Avisar a quien aprueba desde N boletos reasignados en el día',
            self::AVISO_ANULACIONES_POR_DIA => 'Avisar a quien aprueba desde N boletos anulados en el día',
        };
    }

    /** ¿Detiene la operación (pide autorización) o solo avisa? */
    public function bloquea(): bool
    {
        return match ($this) {
            self::CORTESIAS_POR_DIA, self::REASIGNACIONES_POR_DIA, self::ANULACIONES_POR_DIA => true,
            default => false,
        };
    }

    /** Lo que se cuenta, en plural ("boletos anulados"), para los avisos. */
    public function cosa(): string
    {
        return match ($this->capacidad()) {
            Capacidad::VENTA_CORTESIA => 'asientos de cortesía',
            Capacidad::BOLETO_REASIGNAR => 'boletos reasignados',
            default => 'boletos anulados',
        };
    }
}
