<?php

declare(strict_types=1);

namespace App\Entity\Enum;

use App\Bitacora\Operacion;

/** Lo que se registra en la bitácora de una `Encomienda` (ADR-035). */
enum OperacionEncomienda: string implements Operacion
{
    case RECIBIDA = "recibida";
    case EMBARCADA = "embarcada";
    case TRANSITO = "transito";
    case DESEMBARCADA = "desembarcada";
    case ENTREGADA = "entregada";
    case ANULADA = "anulada";
    /** Cambió la ruta mientras esperaba en bodega (pendientes de envío). */
    case RUTA_CAMBIADA = "ruta_cambiada";

    public static function deEstado(EstadoEncomienda $estado): self
    {
        return self::from($estado->value);
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::RECIBIDA => "Encomienda recibida",
            self::EMBARCADA => "Embarcada",
            self::TRANSITO => "En tránsito",
            self::DESEMBARCADA => "Desembarcada",
            self::ENTREGADA => "Entregada",
            self::ANULADA => "Encomienda anulada",
            self::RUTA_CAMBIADA => "Cambio de ruta",
        };
    }
}
