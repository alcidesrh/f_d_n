<?php

declare(strict_types=1);

namespace App\Acceso;

/** Una asignación vigente, ya resuelta: su rol y su ámbito (puro). */
final class Concesion
{
    /**
     * @param list<string>       $capacidades
     * @param array<string, int> $limites
     */
    public function __construct(
        public readonly int $asignacion,
        public readonly int $rol,
        public readonly string $rolNombre,
        public readonly bool $sistema,
        public readonly array $capacidades,
        public readonly array $limites,
        public readonly Ambito $ambito,
    ) {}

    /** @param list<string> $catalogo todas las capacidades (las del rol de sistema) */
    public function concede(string $capacidad, array $catalogo = []): bool
    {
        return $this->sistema ? in_array($capacidad, $catalogo, true) || $catalogo === [] : in_array($capacidad, $this->capacidades, true);
    }
}
