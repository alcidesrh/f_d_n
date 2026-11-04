<?php

declare(strict_types=1);

namespace App\Metadata;

use App\Excepcion\Rechazo;

/**
 * Motivo por el que no se publicó a mano, con un mensaje apto para el
 * usuario. Sin atrapar, se responde como `{ error, codigo }`.
 */
final class PublicacionRechazada extends Rechazo
{
    public function __construct(string $mensaje, string $codigo = "publicacion_invalida", int $estadoHttp = 422)
    {
        parent::__construct($mensaje, $codigo, $estadoHttp);
    }
}
