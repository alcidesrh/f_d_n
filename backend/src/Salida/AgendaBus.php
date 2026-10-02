<?php

declare(strict_types=1);

namespace App\Salida;

/**
 * Cuándo está ocupado cada bus: un intervalo [salida, salida + duración del
 * trayecto) por salida. Sirve para no programar ni mover un bus a una hora
 * en que todavía va en otro viaje. Si no se conoce la duración, el bus solo
 * choca consigo mismo a la misma hora exacta. Puro.
 */
final class AgendaBus
{
    /** @var array<int, list<array{0: int, 1: int, 2: mixed}>> por bus: [inicio, fin, referencia] en segundos */
    private array $porBus = [];

    public function ocupar(int $busId, \DateTimeInterface $salida, ?int $minutos, mixed $referencia): void
    {
        $this->porBus[$busId][] = self::intervalo($salida, $minutos, $referencia);
    }

    /** La referencia de lo que ya ocupa el bus en ese intervalo, o null si está libre. */
    public function choque(int $busId, \DateTimeInterface $salida, ?int $minutos): mixed
    {
        [$inicio, $fin] = self::intervalo($salida, $minutos, null);
        foreach ($this->porBus[$busId] ?? [] as [$i, $f, $ref]) {
            if ($inicio < $f && $i < $fin) {
                return $ref;
            }
        }

        return null;
    }

    /** @return array{0: int, 1: int, 2: mixed} */
    private static function intervalo(\DateTimeInterface $salida, ?int $minutos, mixed $referencia): array
    {
        $inicio = $salida->getTimestamp();

        return [$inicio, $inicio + max(1, (int) $minutos) * 60, $referencia];
    }
}
