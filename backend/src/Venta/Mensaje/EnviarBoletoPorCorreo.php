<?php

declare(strict_types=1);

namespace App\Venta\Mensaje;

/** Enviar al cliente el boleto (con los datos de la factura) en PDF. Asíncrono. */
final readonly class EnviarBoletoPorCorreo
{
    /**
     * @param list<int> $otrasVentas ventas que viajan en el mismo correo y PDF (el regreso de una compra web)
     */
    public function __construct(
        public int $ventaId,
        public array $otrasVentas = [],
    ) {}
}
