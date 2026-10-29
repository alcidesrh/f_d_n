<?php

declare(strict_types=1);

namespace App\Reporte\Diario;

/** Un reporte diario para un usuario (cola `pesadas`, ADR-044). `dia`: AAAA-MM-DD. */
final readonly class EnviarReporteDiario
{
    public function __construct(
        public int $usuarioId,
        public string $reporte,
        public string $dia,
    ) {}
}
