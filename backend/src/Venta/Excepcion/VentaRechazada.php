<?php

declare(strict_types=1);

namespace App\Venta\Excepcion;

/**
 * Motivo por el que no se pudo vender/reservar, con un mensaje apto para el
 * usuario final. Los controladores la traducen a `{ error, codigo, ... }`.
 */
class VentaRechazada extends \RuntimeException
{
    /**
     * @param array<string, mixed> $detalle datos extra para el cliente de la API
     */
    public function __construct(
        string $mensaje,
        public readonly string $codigo = "venta_invalida",
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
