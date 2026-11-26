<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Qué tan grave es una afectación de un tipo (ADR-070). Ordena las listas y
 * los avisos (lo urgente primero) y sugiere el ritmo del centinela al crear
 * un tipo; el ritmo que rige es el que guarda el tipo.
 */
enum PrioridadAfectacion: string
{
    case NORMAL = "normal";
    case GRAVE = "grave";
    case URGENTE = "urgente";

    public function etiqueta(): string
    {
        return match ($this) {
            self::NORMAL => "Normal",
            self::GRAVE => "Grave",
            self::URGENTE => "Urgente",
        };
    }

    /** Para ordenar: lo urgente primero. */
    public function peso(): int
    {
        return match ($this) {
            self::URGENTE => 0,
            self::GRAVE => 1,
            self::NORMAL => 2,
        };
    }

    /** Cada cuántos minutos insiste el centinela, si el tipo no dice otra cosa. */
    public function intervaloSugerido(): int
    {
        return match ($this) {
            self::URGENTE => 30,
            self::GRAVE => 4 * 60,
            self::NORMAL => 24 * 60,
        };
    }

    /** A los cuántos minutos sin resolver se escala, si el tipo no dice otra cosa. */
    public function escalarSugerido(): int
    {
        return match ($this) {
            self::URGENTE => 2 * 60,
            self::GRAVE => 24 * 60,
            self::NORMAL => 72 * 60,
        };
    }
}
