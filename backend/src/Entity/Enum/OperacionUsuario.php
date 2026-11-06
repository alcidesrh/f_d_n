<?php

declare(strict_types=1);

namespace App\Entity\Enum;

use App\Bitacora\Operacion;

/** Lo que se registra en la bitácora de accesos de un `Usuario` (ADR-039). */
enum OperacionUsuario: string implements Operacion
{
    case ASIGNACION_OTORGADA = "asignacion_otorgada";
    case ASIGNACION_MODIFICADA = "asignacion_modificada";
    case ASIGNACION_REVOCADA = "asignacion_revocada";
    case ACTIVADO = "activado";
    case DESACTIVADO = "desactivado";
    case CONTRASENA = "contrasena";
    case ESTACION_TRABAJO = "estacion_trabajo";
    case SUPLANTACION_INICIO = "suplantacion_inicio";
    case SUPLANTACION_FIN = "suplantacion_fin";

    public function etiqueta(): string
    {
        return match ($this) {
            self::ASIGNACION_OTORGADA => "Rol otorgado",
            self::ASIGNACION_MODIFICADA => "Asignación modificada",
            self::ASIGNACION_REVOCADA => "Rol revocado",
            self::ACTIVADO => "Usuario activado",
            self::DESACTIVADO => "Usuario desactivado",
            self::CONTRASENA => "Contraseña cambiada por un administrador",
            self::ESTACION_TRABAJO => "Cambio de estación de trabajo",
            self::SUPLANTACION_INICIO => "Un administrador del sistema entró a la cuenta",
            self::SUPLANTACION_FIN => "El administrador del sistema salió de la cuenta",
        };
    }
}
