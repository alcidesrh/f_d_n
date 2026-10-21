<?php

declare(strict_types=1);

namespace App\Metadata;

/**
 * Registro que el frontend guarda en el navegador como metadata (ADR-036):
 * guardarlo cambia lo que ven los listados y formularios de una entidad.
 * `VigilanteConfiguracion` anota la entidad al hacer flush, sin conocer las
 * clases que lo implementan.
 */
interface ConfiguracionDelCliente
{
    /** Entidad cuya configuración cambia (`Bus`); null si no se sabe: se revisan todas. */
    public function entidadConfigurada(): ?string;
}
