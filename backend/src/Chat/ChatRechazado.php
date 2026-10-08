<?php

declare(strict_types=1);

namespace App\Chat;

/** Operación del chat no permitida o con datos inválidos (→ 422/403). */
final class ChatRechazado extends \DomainException
{
    public function __construct(string $mensaje, public readonly int $estado = 422)
    {
        parent::__construct($mensaje);
    }
}
