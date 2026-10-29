<?php

declare(strict_types=1);

namespace App\Venta\Facturacion;

use App\Programador\MensajeDeTarea;
use App\Programador\Tarea;

/** `Tarea::CERTIFICAR_PENDIENTES`: facturas en contingencia o fallidas tras cobrar (ADR-044). */
final class CertificarPendientes implements MensajeDeTarea
{
    public function tarea(): Tarea
    {
        return Tarea::CERTIFICAR_PENDIENTES;
    }
}
