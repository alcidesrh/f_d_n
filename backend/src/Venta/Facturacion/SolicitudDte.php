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
     * @param list<ItemDte>               $items
     * @param list<array{0: int, 1: int}> $frases pares `[tipo, escenario]` de la SAT
     */
    public function __construct(
        /**
         * Código interno único de la venta. El certificador rechaza un
         * código ya certificado: así un reintento no emite dos facturas.
         */
        public string $referenciaInterna,
        public \DateTimeImmutable $fechaEmision,
        public string $emisorNit,
        public string $emisorNombre,
        public ?string $emisorDireccion,
        /** Nombre de la estación (o canal) desde donde se emite. */
        public ?string $establecimiento,
        public string $receptorNit,
        public string $receptorNombre,
        public ?string $receptorCorreo,
        public array $items,
        public Money $total,
        /** Número de establecimiento SAT del emisor. */
        public int $establecimientoCodigo = 1,
        public string $afiliacionIva = "GEN",
        public array $frases = [[1, 1]],
        /** Número de acceso de contingencia (DTE emitido antes, certificado ahora). */
        public ?int $numeroAcceso = null,
        /** Ruta del viaje (adenda informativa). */
        public ?string $ruta = null,
    ) {}
}
