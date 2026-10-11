<?php

declare(strict_types=1);

namespace App\Ruta;

/**
 * Motivo por el que no se pudo guardar, duplicar o eliminar una ruta, con un
 * mensaje apto para el usuario. El controlador la traduce a `{ error, codigo }`.
 */
final class RutaRechazada extends \RuntimeException
{
    public function __construct(
        string $mensaje,
        public readonly string $codigo = "ruta_invalida",
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
