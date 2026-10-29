<?php

declare(strict_types=1);

namespace App\Vigilancia;

/**
 * Un usuario llegó hoy al número de aviso que su rol fija a una capacidad
 * (`Limite` de aviso, ADR-044). Se emite después del commit, una vez por día
 * y límite: al cruzarlo. Lo traduce a un aviso del chat `AvisosDelSistema`.
 */
final readonly class UmbralDiarioAlcanzado
{
    /**
     * @param array<string, mixed>                $donde    `Ubicacion::toArray()` de la operación que lo cruzó
     * @param list<array{tipo: string, id: int}> $tarjetas los registros de esa operación
     */
    public function __construct(
        public int $usuarioId,
        public string $limite,
        public int $umbral,
        public int $hechas,
        public array $donde,
        public array $tarjetas = [],
    ) {}
}
