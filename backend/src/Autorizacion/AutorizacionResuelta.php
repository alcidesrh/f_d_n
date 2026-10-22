<?php

declare(strict_types=1);

namespace App\Autorizacion;

/**
 * Una autorización se aprobó, rechazó o retiró (ya confirmado): avisar a quien
 * la pidió y refrescar la bandeja de quienes la podían resolver.
 */
final class AutorizacionResuelta
{
    public function __construct(
        public readonly int $id,
    ) {}
}
