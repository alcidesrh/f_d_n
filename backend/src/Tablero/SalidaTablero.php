<?php

declare(strict_types=1);

namespace App\Tablero;

/**
 * Una salida vista por el tablero: capacidad y lo vendido medidos en
 * asiento-tramos (ADR-030), así un asiento revendido por tramos cuenta lo
 * que de verdad ocupa.
 */
final class SalidaTablero
{
    public function __construct(
        public readonly int $id,
        public readonly \DateTimeImmutable $fecha,
        public readonly string $estado,
        public readonly int $rutaId,
        public readonly string $ruta,
        public readonly ?string $bus,
        public readonly ?int $busId,
        public readonly ?string $empresa,
        public readonly int $asientos,
        /** Tramos entre paradas consecutivas (última parada por orden). */
        public readonly int $tramos,
        public readonly ?\DateTimeImmutable $llegada,
        /** Asiento-tramos vendidos (boletos que no liberan el asiento). */
        public readonly int $ocupado,
        public readonly int $boletos,
        /** Centavos de los boletos que no liberan el asiento, sin cortesías ni vouchers. */
        public readonly int $ingresos,
        /** Boletos no presentados (`cancelado`). */
        public readonly int $noPresentados,
    ) {}

    public function capacidad(): int
    {
        return $this->asientos * max(1, $this->tramos);
    }

    /** Fracción vendida (0..1), o null si no se conoce la capacidad del bus. */
    public function ocupacion(): ?float
    {
        $capacidad = $this->capacidad();

        return $this->asientos === 0 ? null : min(1.0, $this->ocupado / $capacidad);
    }

    public function cancelada(): bool
    {
        return $this->estado === "cancelada";
    }
}
