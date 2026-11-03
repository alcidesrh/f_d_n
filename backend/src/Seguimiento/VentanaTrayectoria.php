<?php

declare(strict_types=1);

namespace App\Seguimiento;

/**
 * Qué lecturas GPS del bus son de una salida (ADR-050). Las lecturas se guardan
 * por bus, sin salida: la salida se reconoce al leerlas, por su horario. Va de
 * un rato antes de la hora de salida (el bus espera y aborda en el origen) a
 * unas horas después de la llegada programada (llega tarde), sin entrar en la
 * siguiente salida del mismo bus.
 */
final readonly class VentanaTrayectoria
{
    public const ANTES_MINUTOS = 30;
    public const DESPUES_HORAS = 3;
    /** Sin llegada programada (o una que no es posterior a la salida), se supone esto. */
    private const SIN_LLEGADA_HORAS = 12;

    private function __construct(
        public int $desde,
        public int $hasta,
    ) {}

    /**
     * @param \DateTimeImmutable|null $llegada  hora programada en la última parada
     * @param \DateTimeImmutable|null $siguiente hora de la siguiente salida del mismo bus
     */
    public static function de(\DateTimeImmutable $partida, ?\DateTimeImmutable $llegada, ?\DateTimeImmutable $siguiente): self
    {
        $conocida = null !== $llegada && $llegada > $partida;
        $llegada = $conocida ? $llegada : $partida->modify('+' . self::SIN_LLEGADA_HORAS . ' hours');
        $hasta = $llegada->modify('+' . self::DESPUES_HORAS . ' hours');
        if (null !== $siguiente) {
            // La siguiente empieza su ventana aquí. Con un horario apretado, al menos hasta la
            // llegada programada; sin ella, manda la siguiente salida.
            $hasta = min($hasta, max($conocida ? $llegada : $partida, $siguiente->modify('-' . self::ANTES_MINUTOS . ' minutes')));
        }

        return new self($partida->modify('-' . self::ANTES_MINUTOS . ' minutes')->getTimestamp(), $hasta->getTimestamp());
    }
}
