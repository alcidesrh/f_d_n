<?php

declare(strict_types=1);

namespace App\Venta;

use Money\Money;

/**
 * Una `BoletoTarifa` reducida a lo que decide su especificidad. El trayecto
 * siempre está fijado; empresa, hora, bus y clase en null son comodín.
 */
final readonly class CandidatoTarifa
{
    public function __construct(
        public int $id,
        public Money $precio,
        public int $trayectoId,
        public ?int $empresaId = null,
        /** `H:i` */
        public ?string $hora = null,
        public ?int $busId = null,
        /** Clase de asiento (`AsientoClase`); null aplica a cualquiera. */
        public ?string $clase = null,
    ) {}
}
