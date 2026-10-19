<?php

declare(strict_types=1);

namespace App\Bitacora;

use App\Entity\BoletoAsiento;
use App\Entity\Encomienda;
use App\Entity\Enum\OperacionBoleto;
use App\Entity\Enum\OperacionEncomienda;
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
    case ENCOMIENDA = "encomienda";

    /** @return class-string */
    public function clase(): string
    {
        return match ($this) {
            self::SALIDA => Salida::class,
            self::BOLETO => BoletoAsiento::class,
            self::ENCOMIENDA => Encomienda::class,
        };
    }

    /** @return class-string<Operacion> */
    public function operaciones(): string
    {
        return match ($this) {
            self::SALIDA => OperacionSalida::class,
            self::BOLETO => OperacionBoleto::class,
            self::ENCOMIENDA => OperacionEncomienda::class,
        };
    }

    public static function deObjeto(object $entidad): ?self
    {
        return match (true) {
            $entidad instanceof Salida => self::SALIDA,
            $entidad instanceof BoletoAsiento => self::BOLETO,
            $entidad instanceof Encomienda => self::ENCOMIENDA,
            default => null,
        };
    }
}
