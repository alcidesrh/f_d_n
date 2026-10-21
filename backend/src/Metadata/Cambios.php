<?php

declare(strict_types=1);

namespace App\Metadata;

/**
 * Qué cambió de una revisión a la siguiente (o qué falta publicar): el schema
 * GraphQL y la configuración de qué entidades.
 */
final class Cambios
{
    /**
     * @param list<string> $agregadas entidades con configuración nueva
     * @param list<string> $modificadas
     * @param list<string> $eliminadas entidades que ya no tienen configuración
     */
    public function __construct(
        public readonly bool $esquema = false,
        public readonly array $agregadas = [],
        public readonly array $modificadas = [],
        public readonly array $eliminadas = [],
    ) {}

    public function vacio(): bool
    {
        return !$this->esquema && $this->agregadas === [] && $this->modificadas === [] && $this->eliminadas === [];
    }

    /** @return array{esquema: bool, agregadas: list<string>, modificadas: list<string>, eliminadas: list<string>} */
    public function toArray(): array
    {
        return [
            "esquema" => $this->esquema,
            "agregadas" => $this->agregadas,
            "modificadas" => $this->modificadas,
            "eliminadas" => $this->eliminadas,
        ];
    }

    /** @param array<string, mixed> $datos */
    public static function desde(array $datos): self
    {
        $lista = static fn(string $clave): array => array_values(array_filter((array) ($datos[$clave] ?? []), "is_string"));

        return new self((bool) ($datos["esquema"] ?? false), $lista("agregadas"), $lista("modificadas"), $lista("eliminadas"));
    }
}
