<?php

declare(strict_types=1);

namespace App\Actividad;

/**
 * Cuánto detalle guarda la bitácora de actividad. La bitácora de negocio
 * (`bitacora`) no depende del nivel: sigue siempre.
 *
 * - mínimo: sesión y las peticiones que escriben (POST, PUT, PATCH, DELETE).
 * - medio: además todas las peticiones y las consultas automáticas agregadas.
 * - máximo: además las acciones de pantalla y el cuerpo de las peticiones
 *   (solo los campos de la lista blanca).
 */
enum NivelActividad: string
{
    case MINIMO = "minimo";
    case MEDIO = "medio";
    case MAXIMO = "maximo";

    public function etiqueta(): string
    {
        return match ($this) {
            self::MINIMO => "Mínimo",
            self::MEDIO => "Medio",
            self::MAXIMO => "Máximo",
        };
    }

    /** @return list<TipoActividad> las familias que registra este nivel */
    public function familias(): array
    {
        return match ($this) {
            self::MINIMO => [TipoActividad::SESION, TipoActividad::PETICION],
            self::MEDIO => [TipoActividad::SESION, TipoActividad::PETICION, TipoActividad::SONDEO],
            self::MAXIMO => TipoActividad::cases(),
        };
    }

    /** Las peticiones que solo leen (GET, HEAD) también. */
    public function lecturas(): bool
    {
        return $this !== self::MINIMO;
    }

    /** El cuerpo de la petición (filtrado por la lista blanca) va al detalle. */
    public function cuerpos(): bool
    {
        return $this === self::MAXIMO;
    }
}
