<?php

declare(strict_types=1);

namespace App\Excepcion;

/**
 * Una operación que el dominio no deja hacer, con un mensaje apto para el
 * usuario (`VentaRechazada`, `SalidaRechazada`…). En una petición HTTP sin
 * atrapar, `RechazoComoJson` la responde como `{ error, codigo, ...detalle }`
 * con su estado: el controlador no necesita su try/catch.
 */
abstract class Rechazo extends \RuntimeException
{
    /**
     * @param array<string, mixed> $detalle datos extra para el cliente de la API
     */
    public function __construct(
        string $mensaje,
        public readonly string $codigo,
        public readonly int $estadoHttp = 422,
        public readonly array $detalle = [],
        ?\Throwable $previa = null,
    ) {
        parent::__construct($mensaje, 0, $previa);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ["error" => $this->getMessage(), "codigo" => $this->codigo, ...$this->detalle];
    }
}
