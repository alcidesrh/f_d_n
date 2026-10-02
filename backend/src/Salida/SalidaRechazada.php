<?php

declare(strict_types=1);

namespace App\Salida;

/**
 * Motivo por el que no se pudo programar, editar, anular o eliminar una
 * salida (o guardar un esquema), con un mensaje apto para el usuario. El
 * controlador la traduce a `{ error, codigo, ... }`.
 */
final class SalidaRechazada extends \RuntimeException
{
    /**
     * @param array<string, mixed> $detalle datos extra para el cliente de la API
     */
    public function __construct(
        string $mensaje,
        public readonly string $codigo = "salida_invalida",
        public readonly int $estadoHttp = 422,
        public readonly array $detalle = [],
    ) {
        parent::__construct($mensaje);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ["error" => $this->getMessage(), "codigo" => $this->codigo, ...$this->detalle];
    }
}
