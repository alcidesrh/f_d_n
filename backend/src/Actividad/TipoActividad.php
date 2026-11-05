<?php

declare(strict_types=1);

namespace App\Actividad;

/**
 * Familias de la bitácora de actividad (`actividad.tipo`). El valor se guarda
 * tal cual: cambiarlo rompe la historia.
 */
enum TipoActividad: string
{
    /** Inicio de sesión, intento fallido, cierre. */
    case SESION = "sesion";
    /** Una petición a la API (REST o GraphQL). */
    case PETICION = "peticion";
    /** Lo que hizo en la pantalla: navegar, imprimir, exportar (lo manda la SPA). */
    case PANTALLA = "pantalla";
    /** Peticiones automáticas (tablero, seguimiento, chat): un contador por minuto, no una fila por petición. */
    case SONDEO = "sondeo";

    public function etiqueta(): string
    {
        return match ($this) {
            self::SESION => "Sesión",
            self::PETICION => "Peticiones a la API",
            self::PANTALLA => "Acciones de pantalla",
            self::SONDEO => "Consultas automáticas",
        };
    }
}
