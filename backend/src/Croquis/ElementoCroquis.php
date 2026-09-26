<?php

declare(strict_types=1);

namespace App\Croquis;

use App\Entity\Asiento;
use App\Entity\BusSenal;
use App\Entity\Enum\AsientoClase;

/**
 * Una celda ocupada del croquis de un bus: un asiento (con número y clase) o
 * una señal (chofer, puerta). `id` es el del `Asiento`/`BusSenal` existente;
 * null para uno nuevo.
 */
final readonly class ElementoCroquis
{
    public const ASIENTO = "asiento";

    public function __construct(
        /** `asiento` o un valor de `TipoBusSenal` (`chofer`, `puerta`). */
        public string $tipo,
        public int $planta,
        public int $fila,
        public int $columna,
        public ?int $numero = null,
        public ?AsientoClase $clase = null,
        public ?int $id = null,
    ) {}

    public function esAsiento(): bool
    {
        return $this->tipo === self::ASIENTO;
    }

    /** Clave de la celda dentro del bus. */
    public function celda(): string
    {
        return sprintf("%d:%d:%d", $this->planta, $this->fila, $this->columna);
    }

    public static function deAsiento(Asiento $asiento): self
    {
        return new self(
            self::ASIENTO,
            $asiento->getPlanta(),
            (int) $asiento->getFila(),
            (int) $asiento->getColumna(),
            $asiento->getNumero(),
            $asiento->getClase(),
            $asiento->getId(),
        );
    }

    public static function deSenal(BusSenal $senal): self
    {
        return new self(
            $senal->getTipo()->value,
            $senal->getPlanta(),
            $senal->getFila(),
            $senal->getColumna(),
            id: $senal->getId(),
        );
    }

    /**
     * Forma JSON del croquis (`GET/PUT /api/buses/{id}/croquis`).
     *
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        $data = [
            "tipo" => $this->tipo,
            "id" => $this->id,
            "planta" => $this->planta,
            "fila" => $this->fila,
            "columna" => $this->columna,
        ];
        if ($this->esAsiento()) {
            $data["numero"] = $this->numero;
            $data["clase"] = $this->clase?->value;
        }

        return $data;
    }
}
