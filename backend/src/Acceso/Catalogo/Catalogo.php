<?php

declare(strict_types=1);

namespace App\Acceso\Catalogo;

use App\Acceso\Capacidad;
use App\Acceso\Dimension;
use App\Acceso\Limite;

/**
 * Todas las capacidades que existen (ADR-039): las del dominio (`Capacidad`)
 * más las del CRUD genérico que declara cada entidad (`#[Recurso]`). Un rol
 * solo puede guardar códigos de aquí, y el voter solo responde por ellos.
 */
final class Catalogo
{
    /** @var array<string, DefinicionCapacidad>|null */
    private ?array $definiciones = null;

    public function __construct(
        private readonly CatalogoRecursos $recursos,
    ) {}

    /** @return array<string, DefinicionCapacidad> por código, en orden de área */
    public function definiciones(): array
    {
        if ($this->definiciones !== null) {
            return $this->definiciones;
        }
        $definiciones = [];
        foreach (Capacidad::cases() as $c) {
            $definiciones[$c->value] = new DefinicionCapacidad(
                $c->value,
                $c->nombre(),
                $c->area(),
                array_map(static fn(Dimension $d) => $d->value, $c->dimensiones()),
                array_map(static fn(Limite $l) => $l->value, $c->limites()),
            );
        }
        foreach ($this->recursos->recursos() as $recurso) {
            foreach ($recurso->capacidades() as $operacion => $codigo) {
                $definiciones[$codigo] ??= new DefinicionCapacidad(
                    $codigo,
                    sprintf('%s: %s', $recurso->nombre, $operacion),
                    $recurso->area,
                    $recurso->empresa !== null ? [Dimension::EMPRESA->value] : [],
                    [],
                    $recurso->nombreCorto,
                    $operacion,
                );
            }
        }

        return $this->definiciones = $definiciones;
    }

    public function existe(string $codigo): bool
    {
        return isset($this->definiciones()[$codigo]);
    }

    /** @return list<string> */
    public function codigos(): array
    {
        return array_keys($this->definiciones());
    }
}
