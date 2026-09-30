<?php

declare(strict_types=1);

namespace App\Venta\Facturacion;

/**
 * Puerto: razón social de un NIT según la SAT (para registrar clientes con
 * el nombre correcto y detectar NIT inexistentes antes de facturar).
 */
interface ConsultaContribuyente
{
    /**
     * Nombre registrado del NIT, o null si no existe / no es válido.
     *
     * @throws CertificacionFallida si el servicio no respondió
     */
    public function nombreDeNit(string $nit): ?string;
}
