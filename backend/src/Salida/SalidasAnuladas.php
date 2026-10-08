<?php

declare(strict_types=1);

namespace App\Salida;

/** Se anularon salidas (ya confirmado en la base de datos). */
final class SalidasAnuladas
{
    /** @param list<int> $ids */
    public function __construct(
        public readonly array $ids,
        public readonly ?int $usuarioId = null,
    ) {}
}
