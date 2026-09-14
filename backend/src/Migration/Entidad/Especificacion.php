<?php

declare(strict_types=1);

namespace App\Migration\Entidad;

/**
 * Parámetros de una ejecución de migración por entidad.
 *
 * - entidad:    nombre clave del migrador (empresa, salida, ...).
 * - desde/hasta: rango de fechas (Y-m-d) — solo para entidades con soportaRangoFechas.
 * - cantidad:    tope de registros a procesar (null = todos los disponibles).
 */
final readonly class Especificacion
{
    public function __construct(
        public string $entidad = "",
        public ?string $desde = null,
        public ?string $hasta = null,
        public ?int $cantidad = null,
    ) {}

    public static function vacia(string $entidad = ""): self
    {
        return new self($entidad);
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArray(array $datos): self
    {
        return new self(
            entidad: (string) ($datos["entidad"] ?? ""),
            desde: empty($datos["desde"]) ? null : (string) $datos["desde"],
            hasta: empty($datos["hasta"]) ? null : (string) $datos["hasta"],
            cantidad: !empty($datos["cantidad"])
                ? max(1, (int) $datos["cantidad"])
                : null,
        );
    }

    /** Crea una copia con otra entidad (mantiene desde/hasta/cantidad). */
    public function para(string $entidad): self
    {
        return new self(
            entidad: $entidad,
            desde: $this->desde,
            hasta: $this->hasta,
            cantidad: $this->cantidad,
        );
    }

    /**
     * @return array{entidad: string, desde: ?string, hasta: ?string, cantidad: ?int}
     */
    public function toArray(): array
    {
        return [
            "entidad" => $this->entidad,
            "desde" => $this->desde,
            "hasta" => $this->hasta,
            "cantidad" => $this->cantidad,
        ];
    }
}
