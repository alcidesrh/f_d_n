<?php

declare(strict_types=1);

namespace App\Metadata;

/**
 * Motivo por el que no se publicó a mano, con un mensaje apto para el
 * usuario. El controlador la traduce a `{ error, codigo }`.
 */
final class PublicacionRechazada extends \RuntimeException
{
    public function __construct(
        string $mensaje,
        public readonly string $codigo = "publicacion_invalida",
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
