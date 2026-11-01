<?php

declare(strict_types=1);

namespace App\Correo;

/** Operación del correo no permitida o con datos inválidos (→ 422/403/404). */
final class CorreoRechazado extends \DomainException
{
    public function __construct(string $mensaje, public readonly int $estado = 422)
    {
        parent::__construct($mensaje);
    }
}
