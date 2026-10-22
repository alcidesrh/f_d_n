<?php

declare(strict_types=1);

namespace App\Autorizacion;

/** Una autorización se usó en la operación que cubría (ya confirmado): la campana de quien la pidió se pone al día. */
final class AutorizacionUsada
{
    public function __construct(
        public readonly int $id,
        public readonly int $solicitante,
    ) {}
}
