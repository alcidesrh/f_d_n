<?php

declare(strict_types=1);

namespace App\Chat\Predeterminado;

/**
 * Lo que sale en un envío de un mensaje predeterminado (ADR-045), antes de
 * sumarle el contenido propio de la instancia.
 */
final readonly class Pieza
{
    /**
     * @param string                                         $clave     por qué sale (`salida:123`, `dia:2026-10-29`): una vez por instancia
     * @param list<array{tipo: string, id: int}>             $tarjetas  registros adjuntos (se resuelven con los permisos del lector)
     * @param list<array{nombre: string, contenido: string}> $generados PDF que se guardan como archivos del chat
     */
    public function __construct(
        public string $clave,
        public string $texto,
        public array $tarjetas = [],
        public array $generados = [],
    ) {}
}
