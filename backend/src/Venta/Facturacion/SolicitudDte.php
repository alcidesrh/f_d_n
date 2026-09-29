<?php

declare(strict_types=1);

namespace App\Venta\Facturacion;

use Money\Money;

/**
 * Lo que el sistema pide certificar: una factura (FACT) por la venta, con un
 * ítem por boleto de asiento. Independiente del certificador.
 */
final readonly class SolicitudDte
{
    /**
     * @param list<ItemDte> $items
     */
    public function __construct(
        /** Identificador interno único de la venta (evita duplicados en reintentos). */
        public string $referenciaInterna,
        public \DateTimeImmutable $fechaEmision,
        public string $emisorNit,
        public string $emisorNombre,
        public ?string $emisorDireccion,
        /** Estación (o canal) desde donde se emite. */
        public ?string $establecimiento,
        public string $receptorNit,
        public string $receptorNombre,
        public ?string $receptorCorreo,
        public array $items,
        public Money $total,
    ) {}
}
