<?php

declare(strict_types=1);

namespace App\Mantenimiento;

/**
 * Cuándo vence un plan de mantenimiento en un bus (ADR-065). Puro.
 *
 * Cuenta desde la última orden del bus con ese plan: vence al cumplirse los
 * kilómetros o los días, lo que llegue primero, y está por vencer cuando le
 * falta el `MARGEN` del intervalo o menos. Sin ninguna orden no hay desde
 * dónde contar: queda «sin registro» y no se trata como vencido.
 */
final class ReglasMantenimiento
{
    public const AL_DIA = "al_dia";
    public const POR_VENCER = "por_vencer";
    public const VENCIDO = "vencido";
    public const SIN_REGISTRO = "sin_registro";

    /** Fracción del intervalo que falta cuando empieza a avisar. */
    public const MARGEN = 0.1;

    /** Del que más urge al que menos. */
    private const ORDEN = [self::VENCIDO, self::POR_VENCER, self::SIN_REGISTRO, self::AL_DIA];

    /**
     * @param ?string $ultimaFecha `AAAA-MM-DD` de la última orden; null si no hay ninguna
     * @param string  $hoy         `AAAA-MM-DD`
     *
     * @return array{estado: string, faltanKm: ?int, faltanDias: ?int} negativos: ya pasó por tanto
     */
    public static function estado(?int $cadaKm, ?int $cadaDias, ?int $ultimoKm, ?string $ultimaFecha, int $odometroKm, string $hoy): array
    {
        if ($ultimaFecha === null) {
            return ["estado" => self::SIN_REGISTRO, "faltanKm" => null, "faltanDias" => null];
        }
        $faltanKm = $cadaKm !== null && $ultimoKm !== null ? $ultimoKm + $cadaKm - $odometroKm : null;
        $faltanDias = $cadaDias !== null
            ? $cadaDias - (int) (new \DateTimeImmutable($ultimaFecha))->diff(new \DateTimeImmutable($hoy))->format("%r%a")
            : null;

        $vencido = ($faltanKm !== null && $faltanKm <= 0) || ($faltanDias !== null && $faltanDias <= 0);
        $porVencer = ($faltanKm !== null && $faltanKm <= (int) ceil((int) $cadaKm * self::MARGEN))
            || ($faltanDias !== null && $faltanDias <= (int) ceil((int) $cadaDias * self::MARGEN));

        return [
            "estado" => $vencido ? self::VENCIDO : ($porVencer ? self::POR_VENCER : self::AL_DIA),
            "faltanKm" => $faltanKm,
            "faltanDias" => $faltanDias,
        ];
    }

    /** El que más urge de varios; sin ninguno, al día. @param list<string> $estados */
    public static function peor(array $estados): string
    {
        foreach (self::ORDEN as $e) {
            if (in_array($e, $estados, true)) {
                return $e;
            }
        }

        return self::AL_DIA;
    }

    /** `vencido hace 320 km`, `le faltan 12 días`, `vencido hace 320 km y 4 días`… para avisos y pantalla. */
    public static function detalle(string $estado, ?int $faltanKm, ?int $faltanDias): string
    {
        $km = static fn(int $n) => number_format(abs($n), 0, ".", ",") . " km";
        $dias = static fn(int $n) => abs($n) === 1 ? "1 día" : abs($n) . " días";
        if ($estado === self::SIN_REGISTRO) {
            return "sin registro";
        }
        if ($estado === self::VENCIDO) {
            $pasado = array_filter([
                $faltanKm !== null && $faltanKm < 0 ? $km($faltanKm) : null,
                $faltanDias !== null && $faltanDias < 0 ? $dias($faltanDias) : null,
            ], static fn(?string $p) => $p !== null);

            return $pasado === [] ? "vence hoy" : "vencido hace " . implode(" y ", $pasado);
        }
        $falta = array_filter([
            $faltanKm !== null ? $km($faltanKm) : null,
            $faltanDias !== null ? $dias($faltanDias) : null,
        ], static fn(?string $p) => $p !== null);

        return $falta === [] ? "al día" : "le faltan " . implode(" o ", $falta);
    }
}
