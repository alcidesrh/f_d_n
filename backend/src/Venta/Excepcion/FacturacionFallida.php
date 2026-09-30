<?php

declare(strict_types=1);

namespace App\Venta\Excepcion;

/**
 * El certificador no emitió la factura y la venta no se registró. El cliente
 * puede cancelar, reintentar o (con permiso) reintentar sin factura
 * electrónica (contingencia: la factura queda pendiente).
 */
final class FacturacionFallida extends VentaRechazada
{
    public static function por(string $motivo, bool $recuperable, bool $permiteSinFactura): self
    {
        return new self(
            $motivo,
            "facturacion",
            502,
            ["recuperable" => $recuperable, "permiteSinFactura" => $permiteSinFactura],
        );
    }
}
