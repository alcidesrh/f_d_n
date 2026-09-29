<?php

declare(strict_types=1);

namespace App\Venta;

use Money\Money;

/** Una `BoletoTarifa` reducida a lo que decide su especificidad. */
final readonly class CandidatoTarifa
{
    public function __construct(
        public int $id,
        public Money $precio,
        public string $clase,
        public ?int $empresaId = null,
        public ?int $trayectoId = null,
        /** `H:i` */
        public ?string $hora = null,
        public ?int $busId = null,
    ) {}
}
