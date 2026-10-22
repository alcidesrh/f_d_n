<?php

declare(strict_types=1);

namespace App\Acceso\Catalogo;

/** Una capacidad del catálogo, como la ve el editor de roles (puro). */
final class DefinicionCapacidad
{
    /**
     * @param list<string> $dimensiones `empresa`, `estacion`
     * @param list<string> $limites     códigos de `Limite`
     */
    public function __construct(
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $area,
        public readonly array $dimensiones = [],
        public readonly array $limites = [],
        /** Entidad de la API genérica que la usa (nombre corto), si es del CRUD. */
        public readonly ?string $recurso = null,
        /** `ver`, `editar` o `eliminar` si es del CRUD genérico. */
        public readonly ?string $operacion = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'area' => $this->area,
            'dimensiones' => $this->dimensiones,
            'limites' => $this->limites,
            'recurso' => $this->recurso,
            'operacion' => $this->operacion,
        ];
    }
}
