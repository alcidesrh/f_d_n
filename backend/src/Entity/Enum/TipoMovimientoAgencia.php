<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Movimiento del saldo de una agencia (`AgenciaMovimiento`). Los créditos
 * suman al saldo, los débitos restan.
 */
enum TipoMovimientoAgencia: string
{
    /** Depósito registrado por un rol autorizado (puede llevar bonificación). */
    case DEPOSITO = "deposito";
    /** Bonificación sobre un depósito (`Agencia.porcentajeBonificacion`). */
    case BONIFICACION = "bonificacion";
    /** Venta de boletos: se descuenta su total. */
    case VENTA = "venta";
    /** Boletos anulados: se devuelve su precio. */
    case ANULACION = "anulacion";
    /** Corrección manual (positiva o negativa). */
    case AJUSTE = "ajuste";
}
