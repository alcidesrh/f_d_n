<?php

declare(strict_types=1);

namespace App\Autorizacion;

/**
 * Motivo por el que no se pudo pedir, aprobar, rechazar, retirar o usar una
 * autorización (ADR-040), con un mensaje apto para el usuario. El
 * controlador la traduce a `{ error, codigo, ... }`.
 */
final class AutorizacionRechazada extends \RuntimeException
{
    /**
     * @param array<string, mixed> $detalle datos extra para el cliente de la API
     */
    public function __construct(
        string $mensaje,
        public readonly string $codigo = "autorizacion_invalida",
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
