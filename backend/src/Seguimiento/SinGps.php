<?php

declare(strict_types=1);

namespace App\Seguimiento;

/** Aún no hay GPS instalados: ninguna lectura. */
final class SinGps implements FuenteGps
{
    public function ultima(string $busCodigo): ?Posicion
    {
        return null;
    }
}
