<?php

declare(strict_types=1);

namespace App\Administracion;

/**
 * Motivo por el que no se pudo guardar, ordenar o eliminar una caja o un
 * enlace de la administración, con un mensaje apto para el usuario. El
 * controlador la traduce a `{ error, codigo }`.
 */
final class AdministracionRechazada extends \RuntimeException
{
    public function __construct(
        string $mensaje,
        public readonly string $codigo = "administracion_invalida",
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
