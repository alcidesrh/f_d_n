<?php

declare(strict_types=1);

namespace App\Administracion;

use App\Excepcion\Rechazo;

/**
 * Motivo por el que no se pudo guardar, ordenar o eliminar una caja o un
 * enlace de la administración, con un mensaje apto para el usuario. Sin
 * atrapar, se responde como `{ error, codigo }`.
 */
final class AdministracionRechazada extends Rechazo
{
    public function __construct(string $mensaje, string $codigo = "administracion_invalida", int $estadoHttp = 422)
    {
        parent::__construct($mensaje, $codigo, $estadoHttp);
    }
}
