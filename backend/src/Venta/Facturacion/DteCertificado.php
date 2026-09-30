<?php

declare(strict_types=1);

namespace App\Venta\Facturacion;

/** Respuesta útil del certificador (lo que se guarda en `Factura` y se imprime). */
final readonly class DteCertificado
{
    public function __construct(
        /** Número de autorización (UUID del DTE). */
        public string $uuid,
        public string $serie,
        public int $numero,
        public \DateTimeImmutable $fechaCertificacion,
        public ?string $certificadorNit = null,
        public ?string $certificadorNombre = null,
        public ?string $emisorNombreComercial = null,
        public ?string $establecimientoCodigo = null,
        public ?string $xml = null,
        /** Representación gráfica del DTE en el portal del certificador. */
        public ?string $urlPdf = null,
    ) {}
}
