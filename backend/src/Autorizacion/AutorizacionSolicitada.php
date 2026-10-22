<?php

declare(strict_types=1);

namespace App\Autorizacion;

/** Se pidió una autorización (ya confirmado en la base de datos): avisar a quienes la pueden resolver. */
final class AutorizacionSolicitada
{
    public function __construct(
        public readonly int $id,
    ) {}
}
