<?php

declare(strict_types=1);

namespace App\Encomienda;

use App\Venta\Excepcion\VentaRechazada;

/**
 * Motivo por el que no se pudo registrar, cotizar o mover una encomienda, con
 * un mensaje apto para el usuario. Extiende `VentaRechazada` para que la venta
 * con boleto la responda igual (`{ error, codigo, ... }`).
 */
final class EncomiendaRechazada extends VentaRechazada
{
    public function __construct(string $mensaje, string $codigo = "encomienda_invalida", int $estadoHttp = 422, array $detalle = [])
    {
        parent::__construct($mensaje, $codigo, $estadoHttp, $detalle);
    }
}
