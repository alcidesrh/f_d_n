<?php

declare(strict_types=1);

namespace App\Bitacora;

use App\Entity\BoletoAsiento;
use App\Entity\Enum\OperacionBoleto;
use App\Entity\Enum\OperacionSalida;
use App\Entity\Salida;

/**
 * Qué registros tienen bitácora. El valor es lo que se guarda en
 * `bitacora.entidad` y lo que va en la URL (`/api/bitacora/{tipo}/{id}`).
 */
enum TipoRegistro: string
{
    case SALIDA = "salida";
    case BOLETO = "boleto";

    /** @return class-string */
    public function clase(): string
    {
        return match ($this) {
            self::SALIDA => Salida::class,
            self::BOLETO => BoletoAsiento::class,
        };
    }

    /** @return class-string<Operacion> */
    public function operaciones(): string
    {
        return match ($this) {
            self::SALIDA => OperacionSalida::class,
            self::BOLETO => OperacionBoleto::class,
        };
    }

    public static function deObjeto(object $entidad): ?self
    {
        return match (true) {
            $entidad instanceof Salida => self::SALIDA,
            $entidad instanceof BoletoAsiento => self::BOLETO,
            default => null,
        };
    }
}
