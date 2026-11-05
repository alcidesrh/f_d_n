<?php

declare(strict_types=1);

namespace App\Actividad;

/**
 * A quién se le sigue la actividad, con lo que hace falta para decidir si
 * entra en la selección: sus roles vigentes, las empresas de su alcance
 * (null = todas) y su estación de trabajo.
 */
final class Sujeto
{
    /**
     * @param list<int>      $roles
     * @param list<int>|null $empresas
     */
    public function __construct(
        public readonly int $usuario,
        public readonly array $roles = [],
        public readonly ?array $empresas = [],
        public readonly ?int $estacion = null,
    ) {}
}
