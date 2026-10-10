<?php

declare(strict_types=1);

namespace App\Entity\Enum;

use App\Bitacora\Operacion;

/** Lo que se registra en la bitácora de una `Salida`. */
enum OperacionSalida: string implements Operacion
{
    case CREADA = "creada";
    case ABORDANDO = "abordando";
    case INICIADA = "iniciada";
    case FINALIZADA = "finalizada";
    case CANCELADA = "cancelada";
    case CAMBIO_BUS = "cambio_bus";
    case ELIMINADA = "eliminada";

    public function etiqueta(): string
    {
        return match ($this) {
            self::CREADA => "Salida creada",
            self::ABORDANDO => "Abordando",
            self::INICIADA => "Salida iniciada",
            self::FINALIZADA => "Salida finalizada",
            self::CANCELADA => "Salida cancelada",
            self::CAMBIO_BUS => "Cambio de bus",
            self::ELIMINADA => "Salida eliminada",
        };
    }

    /** La operación que deja un cambio de estado de la salida; null si no se registra. */
    public static function deEstado(EstadoSalida $estado): ?self
    {
        return match ($estado) {
            EstadoSalida::PROGRAMADA => null,
            EstadoSalida::ABORDANDO => self::ABORDANDO,
            EstadoSalida::INICIADA => self::INICIADA,
            EstadoSalida::FINALIZADA => self::FINALIZADA,
            EstadoSalida::CANCELADA => self::CANCELADA,
        };
    }
}
