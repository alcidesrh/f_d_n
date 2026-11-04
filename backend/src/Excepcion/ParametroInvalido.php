<?php

declare(strict_types=1);

namespace App\Excepcion;

/** Un parámetro de la petición mal formado (`App\Http\Parametros`): 400. */
final class ParametroInvalido extends Rechazo
{
    public function __construct(string $mensaje, string $codigo = "parametro_invalido", int $estadoHttp = 400)
    {
        parent::__construct($mensaje, $codigo, $estadoHttp);
    }
}
