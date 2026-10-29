<?php

declare(strict_types=1);

namespace App\Reporte\Diario;

use App\Acceso\Alcance;
use App\Acceso\Ambito;

/**
 * Las piezas en que se arma un reporte programado para un destinatario
 * según su alcance (puro, ADR-044). Cada celda es `{empresa, estacion}` con
 * null = todas. Una celda que otra ya cubre se quita (no se manda dos veces
 * lo mismo). Los ámbitos de agencia no cuentan: los reportes de caja son de
 * empresas y estaciones.
 */
final class Celdas
{
    /** @return list<array{empresa: ?int, estacion: ?int}> */
    public static function de(Alcance $alcance, string $nivel): array
    {
        $celdas = [];
        foreach ($alcance->ambitos() as $a) {
            if ($a->agencia !== null) {
                continue;
            }
            if ($nivel === ReporteProgramado::EMPRESA) {
                // Por empresa entera: un alcance acotado a estaciones no ve la empresa completa.
                if ($a->estaciones !== null) {
                    continue;
                }
                foreach ($a->empresas ?? [null] as $e) {
                    $celdas[] = ["empresa" => $e, "estacion" => null];
                }
                continue;
            }
            foreach ($a->empresas ?? [null] as $e) {
                foreach ($a->estaciones ?? [null] as $s) {
                    $celdas[] = ["empresa" => $e, "estacion" => $s];
                }
            }
        }

        return self::sinCubiertas($celdas);
    }

    /**
     * @param list<array{empresa: ?int, estacion: ?int}> $celdas
     *
     * @return list<array{empresa: ?int, estacion: ?int}>
     */
    private static function sinCubiertas(array $celdas): array
    {
        $unicas = [];
        foreach ($celdas as $c) {
            $unicas[sprintf("%s|%s", $c["empresa"] ?? "*", $c["estacion"] ?? "*")] = $c;
        }
        $cubre = static fn(array $a, array $b) => $a !== $b
            && ($a["empresa"] === null || $a["empresa"] === $b["empresa"])
            && ($a["estacion"] === null || $a["estacion"] === $b["estacion"]);
        $resultado = array_values(array_filter($unicas, static function (array $c) use ($unicas, $cubre): bool {
            foreach ($unicas as $otra) {
                if ($cubre($otra, $c)) {
                    return false;
                }
            }

            return true;
        }));
        usort($resultado, static fn(array $a, array $b) => [$a["empresa"] ?? 0, $a["estacion"] ?? 0] <=> [$b["empresa"] ?? 0, $b["estacion"] ?? 0]);

        return $resultado;
    }

    /** Un ámbito con solo esa celda: para recortar las consultas que piden un alcance. */
    public static function alcanceDe(array $celda): Alcance
    {
        return new Alcance([new Ambito(
            $celda["empresa"] === null ? null : [$celda["empresa"]],
            $celda["estacion"] === null ? null : [$celda["estacion"]],
        )]);
    }
}
