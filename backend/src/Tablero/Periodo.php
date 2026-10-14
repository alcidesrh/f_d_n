<?php

declare(strict_types=1);

namespace App\Tablero;

/**
 * Ventana que mira el tablero y la que usa para comparar. El periodo actual
 * son los últimos `dias` días hasta ahora (hoy incluido, a medias); el
 * anterior es la misma ventana desplazada y cortada a la misma hora, para que
 * la comparación sea pareja a cualquier hora del día. "Hoy" se compara con el
 * mismo día de la semana pasada: la demanda de buses va por día de la semana.
 */
final class Periodo
{
    public const DIAS = [1, 7, 30, 90];

    /** Días que mira como mínimo el mapa de demanda (con menos, cada celda es una sola salida). */
    public const DIAS_DEMANDA = 28;

    public readonly \DateTimeImmutable $inicio;
    public readonly \DateTimeImmutable $inicioAnterior;
    public readonly \DateTimeImmutable $finAnterior;
    /** Días que se desplaza el periodo anterior. */
    public readonly int $desfase;

    public function __construct(
        public readonly int $dias,
        public readonly \DateTimeImmutable $ahora,
    ) {
        if (!in_array($dias, self::DIAS, true)) {
            throw new \InvalidArgumentException(sprintf("Periodo no disponible; usa %s días.", implode(", ", self::DIAS)));
        }
        $hoy = $ahora->setTime(0, 0);
        $this->inicio = $hoy->modify(sprintf("-%d days", $dias - 1));
        $this->desfase = $dias === 1 ? 7 : $dias;
        $this->inicioAnterior = $this->inicio->modify("-{$this->desfase} days");
        $this->finAnterior = $ahora->modify("-{$this->desfase} days");
    }

    public static function desde(mixed $dias, \DateTimeImmutable $ahora): self
    {
        $n = is_scalar($dias) && ctype_digit((string) $dias) ? (int) $dias : 7;

        return new self($n, $ahora);
    }

    public function hoy(): \DateTimeImmutable
    {
        return $this->ahora->setTime(0, 0);
    }

    /** Hoy se mira por hora; los demás periodos, por día. */
    public function porHora(): bool
    {
        return $this->dias === 1;
    }

    /** Desde cuándo mira el mapa de demanda (al menos cuatro semanas). */
    public function inicioDemanda(): \DateTimeImmutable
    {
        return $this->dias >= self::DIAS_DEMANDA ? $this->inicio : $this->hoy()->modify(sprintf("-%d days", self::DIAS_DEMANDA - 1));
    }

    /** Primer instante que hace falta leer de la base. */
    public function inicioLectura(): \DateTimeImmutable
    {
        return min($this->inicioAnterior, $this->inicioDemanda());
    }

    public function rotuloAnterior(): string
    {
        return match ($this->dias) {
            1 => "mismo día de la semana pasada",
            7 => "7 días anteriores",
            default => "{$this->dias} días anteriores",
        };
    }

    /** Llave del cubo (hora o día) del periodo actual al que corresponde un instante del anterior. */
    public function alActual(\DateTimeImmutable $t): \DateTimeImmutable
    {
        return $t->modify("+{$this->desfase} days");
    }

    public function esActual(\DateTimeImmutable $t): bool
    {
        return $t >= $this->inicio && $t <= $this->ahora;
    }

    public function esAnterior(\DateTimeImmutable $t): bool
    {
        return $t >= $this->inicioAnterior && $t <= $this->finAnterior;
    }
}
