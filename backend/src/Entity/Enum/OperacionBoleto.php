<?php

declare(strict_types=1);

namespace App\Entity\Enum;

use App\Bitacora\Operacion;

/** Lo que se registra en la bitácora de un `BoletoAsiento`. */
enum OperacionBoleto: string implements Operacion
{
    case CREADO = "creado";
    case ANULADO = "anulado";
    case REASIGNADO = "reasignado";
    case CHEQUEADO = "chequeado";
    case TRANSITO = "transito";
    case CANCELADO = "cancelado";
    case FINALIZADO = "finalizado";
    case REVERTIDO = "revertido";
    case POSPUESTO = "pospuesto";
    /** Autorizaciones sobre el boleto (ADR-040); su uso va en el detalle de la anulación o reasignación. */
    case AUTORIZACION_SOLICITADA = "autorizacion_solicitada";
    case AUTORIZACION_APROBADA = "autorizacion_aprobada";
    case AUTORIZACION_RECHAZADA = "autorizacion_rechazada";

    /** La que se anota cuando el boleto pasa a `$estado` (lo usan la bitácora y la sincronización con el legado). */
    public static function alPasarA(EstadoBoletoAsiento $estado): self
    {
        return match ($estado) {
            EstadoBoletoAsiento::ANULADO => self::ANULADO,
            EstadoBoletoAsiento::REASIGNADO => self::REASIGNADO,
            EstadoBoletoAsiento::CHEQUEADO => self::CHEQUEADO,
            EstadoBoletoAsiento::TRANSITO => self::TRANSITO,
            EstadoBoletoAsiento::CANCELADO => self::CANCELADO,
            EstadoBoletoAsiento::FINALIZADO => self::FINALIZADO,
            EstadoBoletoAsiento::POSPUESTO => self::POSPUESTO,
            // Solo se vuelve a emitido revirtiendo un cancelado (`TransicionBoleto::REVERTIR`).
            EstadoBoletoAsiento::EMITIDO => self::REVERTIDO,
        };
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::CREADO => "Boleto creado",
            self::ANULADO => "Boleto anulado",
            self::REASIGNADO => "Boleto reasignado",
            self::CHEQUEADO => "Boleto chequeado",
            self::TRANSITO => "En tránsito",
            self::CANCELADO => "No se presentó a abordar",
            self::FINALIZADO => "Viaje finalizado",
            self::REVERTIDO => "Cancelación revertida",
            self::POSPUESTO => "Viaje pospuesto",
            self::AUTORIZACION_SOLICITADA => "Autorización solicitada",
            self::AUTORIZACION_APROBADA => "Autorización aprobada",
            self::AUTORIZACION_RECHAZADA => "Autorización rechazada",
        };
    }
}
