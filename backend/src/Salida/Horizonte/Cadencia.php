<?php

declare(strict_types=1);

namespace App\Salida\Horizonte;

/**
 * Las cuentas del horizonte de un esquema automático (puro, ADR-044): qué
 * días tocan según el ancla y el intervalo, y en qué tramos se despliega una
 * ventana. Cada tramo es una transacción corta (a lo sumo `TRAMO_DIAS`, o un
 * intervalo si es más largo) que empieza en un día de la cadencia.
 */
final class Cadencia
{
    public const TRAMO_DIAS = 14;

    /** El primer día de la cadencia que no es anterior a `$desde`. */
    public static function siguiente(\DateTimeImmutable $ancla, int $intervalo, \DateTimeImmutable $desde): \DateTimeImmutable
    {
        $ancla = $ancla->setTime(0, 0);
        $desde = $desde->setTime(0, 0);
        if ($desde <= $ancla) {
            return $ancla;
        }
        $resto = self::dias($ancla, $desde) % $intervalo;

        return $resto === 0 ? $desde : $desde->modify('+' . ($intervalo - $resto) . ' days');
    }

    /**
     * Lo que falta desplegar para llegar al horizonte: desde el día después de
     * la frontera (o hoy, si la frontera quedó atrás) hasta hoy + horizonte − 1.
     *
     * @return array{desde: \DateTimeImmutable, hasta: \DateTimeImmutable}|null null: ya está al día
     */
    public static function ventana(\DateTimeImmutable $hoy, int $horizonteDias, ?\DateTimeImmutable $frontera): ?array
    {
        $hoy = $hoy->setTime(0, 0);
        $hasta = $hoy->modify('+' . ($horizonteDias - 1) . ' days');
        $desde = $frontera === null ? $hoy : max($hoy, $frontera->setTime(0, 0)->modify('+1 day'));

        return $desde > $hasta ? null : ['desde' => $desde, 'hasta' => $hasta];
    }

    /**
     * La ventana partida en tramos que empiezan en un día de la cadencia.
     * Vacía si en la ventana no cae ningún día.
     *
     * @return list<array{desde: \DateTimeImmutable, hasta: \DateTimeImmutable}>
     */
    public static function tramos(\DateTimeImmutable $ancla, int $intervalo, \DateTimeImmutable $desde, \DateTimeImmutable $hasta): array
    {
        $hasta = $hasta->setTime(0, 0);
        $largo = $intervalo * max(1, intdiv(self::TRAMO_DIAS, $intervalo));
        $tramos = [];
        for ($d = self::siguiente($ancla, $intervalo, $desde); $d <= $hasta; $d = $d->modify("+{$largo} days")) {
            $tramos[] = ['desde' => $d, 'hasta' => min($hasta, $d->modify('+' . ($largo - 1) . ' days'))];
        }

        return $tramos;
    }

    private static function dias(\DateTimeImmutable $a, \DateTimeImmutable $b): int
    {
        // Por fecha de calendario: inmune a horas y cambios de horario.
        return (int) ((\DateTimeImmutable::createFromFormat('!Y-m-d', $b->format('Y-m-d'), new \DateTimeZone('UTC'))->getTimestamp()
            - \DateTimeImmutable::createFromFormat('!Y-m-d', $a->format('Y-m-d'), new \DateTimeZone('UTC'))->getTimestamp()) / 86400);
    }
}
