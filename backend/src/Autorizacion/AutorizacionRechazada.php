<?php

declare(strict_types=1);

namespace App\Autorizacion;

use App\Excepcion\Rechazo;

/**
 * Motivo por el que no se pudo pedir, aprobar, rechazar, retirar o usar una
 * autorización (ADR-040), con un mensaje apto para el usuario. Sin
 * atrapar, se responde como `{ error, codigo, ... }`.
 */
final class AutorizacionRechazada extends Rechazo
{
    /**
     * @param array<string, mixed> $detalle datos extra para el cliente de la API
     */
    public function __construct(string $mensaje, string $codigo = "autorizacion_invalida", int $estadoHttp = 422, array $detalle = [])
    {
        parent::__construct($mensaje, $codigo, $estadoHttp, $detalle);
    }
}
