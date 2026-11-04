<?php

declare(strict_types=1);

namespace App\Reporte;

use App\Excepcion\Rechazo;

/** Parámetros de un reporte inválidos; se responde como `{ error, codigo }`. */
final class ReporteRechazado extends Rechazo
{
    public function __construct(string $mensaje, string $codigo = "reporte_invalido", int $estadoHttp = 422)
    {
        parent::__construct($mensaje, $codigo, $estadoHttp);
    }
}
