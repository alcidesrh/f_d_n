<?php

declare(strict_types=1);

namespace App\Venta\Mensaje;

/** Enviar al cliente el boleto (con los datos de la factura) en PDF. Asíncrono. */
final readonly class EnviarBoletoPorCorreo
{
    public function __construct(
        public int $ventaId,
    ) {}
}
