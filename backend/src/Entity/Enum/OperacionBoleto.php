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

    public function etiqueta(): string
    {
        return match ($this) {
            self::CREADO => "Boleto creado",
            self::ANULADO => "Boleto anulado",
            self::REASIGNADO => "Boleto reasignado",
        };
    }
}
