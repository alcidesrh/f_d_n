<?php

declare(strict_types=1);

namespace App\Reporte\Diario;

use App\Acceso\Capacidad;

/**
 * Lo que el programador de tareas manda solo a quien se suscribe (ADR-044).
 * Catálogo cerrado: cada uno es un reporte o aviso del dominio con la
 * capacidad que exige y el nivel al que se arma. Quién lo recibe y por dónde
 * (chat, correo) lo decide cada usuario en Mi cuenta (`SuscripcionReporte`);
 * el contenido siempre se recorta al alcance que tiene en ese momento.
 *
 * - `empresa`: uno por empresa entera de su alcance (como el tablero); quien
 *   solo tiene estaciones no lo recibe.
 * - `estacion`: uno por cada estación de su alcance (o por empresa, si la
 *   tiene entera); los que salen vacíos no se mandan.
 */
enum ReporteProgramado: string
{
    case RESUMEN_DIARIO = 'resumen_diario';
    case CUADRE_BOLETOS = 'cuadre_boletos';
    case CUADRE_ENCOMIENDAS = 'cuadre_encomiendas';
    case ALERTAS_OPERACION = 'alertas_operacion';

    public const EMPRESA = 'empresa';
    public const ESTACION = 'estacion';

    public function nombre(): string
    {
        return match ($this) {
            self::RESUMEN_DIARIO => 'Resumen del día',
            self::CUADRE_BOLETOS => 'Cuadre de venta de boletos',
            self::CUADRE_ENCOMIENDAS => 'Cuadre de encomiendas',
            self::ALERTAS_OPERACION => 'Alertas de la operación',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::RESUMEN_DIARIO => 'Cada mañana, el día anterior de cada empresa: ingresos, boletos, ocupación, anulaciones y cortesías (y quién las hizo) y lo que pide acción.',
            self::CUADRE_BOLETOS => 'Cada mañana, el PDF del cuadre de venta de boletos del día anterior de cada estación.',
            self::CUADRE_ENCOMIENDAS => 'Cada mañana, el PDF del cuadre de encomiendas del día anterior de cada estación.',
            self::ALERTAS_OPERACION => 'Durante el día, en cuanto aparece: facturas sin certificar, salidas sin cerrar, buses con tarjeta vencida, agencias sin saldo, anulaciones altas…',
        };
    }

    public function capacidad(): Capacidad
    {
        return match ($this) {
            self::CUADRE_ENCOMIENDAS => Capacidad::REPORTE_ENCOMIENDAS,
            default => Capacidad::REPORTE_VENTAS,
        };
    }

    public function nivel(): string
    {
        return match ($this) {
            self::CUADRE_BOLETOS, self::CUADRE_ENCOMIENDAS => self::ESTACION,
            default => self::EMPRESA,
        };
    }

    /** ¿Sale cada mañana (los demás, cuando ocurren)? */
    public function diario(): bool
    {
        return $this !== self::ALERTAS_OPERACION;
    }
}
