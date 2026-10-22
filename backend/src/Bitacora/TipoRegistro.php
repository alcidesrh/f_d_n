<?php

declare(strict_types=1);

namespace App\Bitacora;

use App\Entity\BoletoAsiento;
use App\Entity\Encomienda;
use App\Entity\Enum\OperacionAutorizacion;
use App\Entity\Enum\OperacionBoleto;
use App\Entity\Enum\OperacionEncomienda;
use App\Entity\Enum\OperacionRol;
use App\Entity\Enum\OperacionSalida;
use App\Entity\Enum\OperacionUsuario;
use App\Entity\Role;
use App\Entity\Salida;
use App\Entity\SolicitudAutorizacion;
use App\Entity\Usuario;

/**
 * Qué registros tienen bitácora. El valor es lo que se guarda en
 * `bitacora.entidad` y lo que va en la URL (`/api/bitacora/{tipo}/{id}`).
 */
enum TipoRegistro: string
{
    case SALIDA = "salida";
    case BOLETO = "boleto";
    case ENCOMIENDA = "encomienda";
    /** Accesos del usuario: asignaciones, activación, contraseña (ADR-039). */
    case USUARIO = "usuario";
    case ROL = "rol";
    /** Autorizaciones (pases de un solo uso, ADR-040). */
    case AUTORIZACION = "autorizacion";

    /** @return class-string */
    public function clase(): string
    {
        return match ($this) {
            self::SALIDA => Salida::class,
            self::BOLETO => BoletoAsiento::class,
            self::ENCOMIENDA => Encomienda::class,
            self::USUARIO => Usuario::class,
            self::ROL => Role::class,
            self::AUTORIZACION => SolicitudAutorizacion::class,
        };
    }

    /** @return class-string<Operacion> */
    public function operaciones(): string
    {
        return match ($this) {
            self::SALIDA => OperacionSalida::class,
            self::BOLETO => OperacionBoleto::class,
            self::ENCOMIENDA => OperacionEncomienda::class,
            self::USUARIO => OperacionUsuario::class,
            self::ROL => OperacionRol::class,
            self::AUTORIZACION => OperacionAutorizacion::class,
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
