<?php

declare(strict_types=1);

namespace App\Ruta;

use App\Excepcion\Rechazo;

/**
 * Motivo por el que no se pudo guardar, duplicar o eliminar una ruta, con un
 * mensaje apto para el usuario. Sin atrapar, se responde como `{ error, codigo }`.
 */
final class RutaRechazada extends Rechazo
{
    public function __construct(string $mensaje, string $codigo = "ruta_invalida", int $estadoHttp = 422)
    {
        parent::__construct($mensaje, $codigo, $estadoHttp);
    }
}
