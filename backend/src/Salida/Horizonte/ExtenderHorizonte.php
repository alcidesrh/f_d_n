<?php

declare(strict_types=1);

namespace App\Salida\Horizonte;

/**
 * Desplegar un esquema automático (cola `pesadas`, ADR-044). Sin `horas`,
 * hasta su horizonte desde la frontera; con `horas`, solo esas horas (las
 * recién agregadas al esquema) desde hoy hasta la frontera.
 */
final readonly class ExtenderHorizonte
{
    /** @param list<string>|null $horas `HH:MM` */
    public function __construct(
        public int $esquemaId,
        public ?array $horas = null,
    ) {}
}
