<?php

declare(strict_types=1);

namespace App\ConfiguracionListado;

use App\Excepcion\Rechazo;

/**
 * Motivo por el que no se pudo crear, guardar o eliminar una configuración
 * del listado, con un mensaje apto para el usuario. Sin atrapar, se
 * responde como `{ error, codigo }`.
 */
final class ConfiguracionRechazada extends Rechazo
{
    public function __construct(string $mensaje, string $codigo = "configuracion_invalida", int $estadoHttp = 422)
    {
        parent::__construct($mensaje, $codigo, $estadoHttp);
    }
}
