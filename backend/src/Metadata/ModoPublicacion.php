<?php

declare(strict_types=1);

namespace App\Metadata;

/**
 * Cuándo se enteran los navegadores de un cambio en la configuración de
 * entidades (`METADATA_PUBLICACION`). El schema GraphQL se publica solo en
 * ambos modos: un schema viejo en el navegador rompe las consultas.
 */
enum ModoPublicacion: string
{
    /** Al terminar la petición o el comando que la guardó. */
    case AUTOMATICA = "automatica";
    /** Cuando un administrador publica en `/configuracion/publicacion`. */
    case MANUAL = "manual";

    /** Un valor desconocido o vacío es automática. */
    public static function desde(string $valor): self
    {
        return self::tryFrom(strtolower(trim($valor))) ?? self::AUTOMATICA;
    }
}
