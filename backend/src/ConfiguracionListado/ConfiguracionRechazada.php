<?php

declare(strict_types=1);

namespace App\ConfiguracionListado;

/**
 * Motivo por el que no se pudo crear, guardar o eliminar una configuración
 * del listado, con un mensaje apto para el usuario. El controlador la
 * traduce a `{ error, codigo }`.
 */
final class ConfiguracionRechazada extends \RuntimeException
{
    public function __construct(
        string $mensaje,
        public readonly string $codigo = "configuracion_invalida",
        public readonly int $estadoHttp = 422,
    ) {
        parent::__construct($mensaje);
    }

    /** @return array{error: string, codigo: string} */
    public function toArray(): array
    {
        return ["error" => $this->getMessage(), "codigo" => $this->codigo];
    }
}
