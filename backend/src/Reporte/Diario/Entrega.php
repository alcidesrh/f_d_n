<?php

declare(strict_types=1);

namespace App\Reporte\Diario;

/**
 * Lo que sale para un destinatario (ADR-044): el texto del aviso del chat,
 * el asunto y el HTML del correo, y los PDF. Puro.
 */
final readonly class Entrega
{
    /**
     * @param list<array{nombre: string, contenido: string}> $archivos PDF
     */
    public function __construct(
        public string $asunto,
        public string $texto,
        public string $html,
        public array $archivos = [],
    ) {}
}
