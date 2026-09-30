<?php

declare(strict_types=1);

namespace App\Venta\Facturacion;

use App\Venta\Comprador;

/** Desarrollo: todo NIT con dígito verificador válido "existe". */
final class ConsultaContribuyenteSimulada implements ConsultaContribuyente
{
    public function nombreDeNit(string $nit): ?string
    {
        return Comprador::nitValido($nit) ? "CONTRIBUYENTE DE PRUEBA {$nit}" : null;
    }
}
