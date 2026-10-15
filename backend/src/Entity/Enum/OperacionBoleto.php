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
        };
    }
}
