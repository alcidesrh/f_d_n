<?php

declare(strict_types=1);

namespace App\Venta\Facturacion;

/**
 * Anulación de un DTE ya certificado (`AnularDteJson` de Forcon). Independiente
 * del certificador.
 */
final readonly class SolicitudAnulacion
{
    public function __construct(
        /** UUID de autorización del DTE a anular (`Factura.uuid`). */
        public string $uuid,
        public string $emisorNit,
        public string $receptorNit,
        /** Fecha y hora de emisión del DTE (`Factura.fecha`). */
        public \DateTimeImmutable $fechaEmision,
        public \DateTimeImmutable $fechaAnulacion,
        public string $motivo,
    ) {}
}
