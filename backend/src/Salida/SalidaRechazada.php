<?php

declare(strict_types=1);

namespace App\Salida;

use App\Excepcion\Rechazo;

/**
 * Motivo por el que no se pudo programar, editar, anular o eliminar una
 * salida (o guardar un esquema), con un mensaje apto para el usuario. Sin
 * atrapar, se responde como `{ error, codigo, ... }`.
 */
final class SalidaRechazada extends Rechazo
{
    /**
     * @param array<string, mixed> $detalle datos extra para el cliente de la API
     */
    public function __construct(string $mensaje, string $codigo = "salida_invalida", int $estadoHttp = 422, array $detalle = [])
    {
        parent::__construct($mensaje, $codigo, $estadoHttp, $detalle);
    }
}
