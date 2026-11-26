<?php

declare(strict_types=1);

namespace App\Inventario;

use App\Excepcion\Rechazo;

/**
 * Por qué no se pudo mover o configurar el inventario (ADR-071), con un
 * mensaje apto para el usuario. Sin atrapar, se responde como
 * `{ error, codigo, ... }`.
 */
final class InventarioRechazado extends Rechazo
{
    /**
     * @param array<string, mixed> $detalle datos extra para el cliente de la API
     */
    public function __construct(string $mensaje, string $codigo = "inventario_invalido", int $estadoHttp = 422, array $detalle = [])
    {
        parent::__construct($mensaje, $codigo, $estadoHttp, $detalle);
    }
}
