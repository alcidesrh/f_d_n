<?php

declare(strict_types=1);

namespace App\Reporte;

/** Parámetros de un reporte inválidos; el controlador la traduce a `{ error, codigo }`. */
final class ReporteRechazado extends \RuntimeException
{
    public function __construct(
        string $mensaje,
        public readonly string $codigo = "reporte_invalido",
        public readonly int $estadoHttp = 422,
    ) {
        parent::__construct($mensaje);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ["error" => $this->getMessage(), "codigo" => $this->codigo];
    }
}
