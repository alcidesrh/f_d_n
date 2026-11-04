<?php

declare(strict_types=1);

namespace App\Venta\Excepcion;

use App\Excepcion\Rechazo;

/**
 * Motivo por el que no se pudo vender/reservar, con un mensaje apto para el
 * usuario final. Sin atrapar, se responde como `{ error, codigo, ... }`.
 */
class VentaRechazada extends Rechazo
{
    /**
     * @param array<string, mixed> $detalle datos extra para el cliente de la API
     */
    public function __construct(
        string $mensaje,
        string $codigo = "venta_invalida",
        int $estadoHttp = 422,
        array $detalle = [],
        ?\Throwable $previa = null,
    ) {
        parent::__construct($mensaje, $codigo, $estadoHttp, $detalle, $previa);
    }
}
