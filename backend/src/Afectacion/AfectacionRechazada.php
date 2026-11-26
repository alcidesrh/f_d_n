<?php

declare(strict_types=1);

namespace App\Afectacion;

use App\Excepcion\Rechazo;

/**
 * Por qué no se pudo reportar, editar o resolver una afectación (ADR-070),
 * con un mensaje apto para el usuario. Sin atrapar, se responde como
 * `{ error, codigo, ... }`.
 */
final class AfectacionRechazada extends Rechazo
{
    /**
     * @param array<string, mixed> $detalle datos extra para el cliente de la API
     */
    public function __construct(string $mensaje, string $codigo = "afectacion_invalida", int $estadoHttp = 422, array $detalle = [])
    {
        parent::__construct($mensaje, $codigo, $estadoHttp, $detalle);
    }
}
