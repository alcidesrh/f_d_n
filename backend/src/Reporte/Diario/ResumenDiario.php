<?php

declare(strict_types=1);

namespace App\Reporte\Diario;

use App\Reporte\Dinero;
use App\Tablero\ConsultaTablero;
use App\Tablero\Tablero;

/**
 * El resumen del día de una empresa (puro, ADR-044): las cifras del tablero
 * de ese día comparadas con el mismo día de la semana anterior, quién hizo
 * las anulaciones, reasignaciones y cortesías, y lo que pide acción. Lo
 * mismo sirve al chat (texto) y al correo (la plantilla recibe la sección).
 */
final class ResumenDiario
{
    private const NIVELES = ["critico" => "Crítico", "alerta" => "Alerta", "aviso" => "Aviso"];

    /**
     * @param array{anulados: list<array{nombre: string, usuario: string, boletos: int}>, reasignados: list<array{nombre: string, usuario: string, boletos: int}>, cortesias: list<array{nombre: string, usuario: string, boletos: int}>} $incidencias
     *
     * @return array<string, mixed>|null null: un día sin venta, sin salidas y sin nada que atender
     */
    public static function seccion(string $empresa, Tablero $t, array $incidencias): ?array
    {
        $k = $t->kpis();
        $alertas = $t->alertas();
        if ($k["boletos"]["actual"] === 0 && $k["salidas"]["actual"] === 0 && $alertas === []) {
            return null;
        }
        $m = ConsultaTablero::MONEDA;

        return [
            "empresa" => $empresa,
            "ingresos" => Dinero::con((int) $k["ingresos"]["actual"], $m),
            "variacion" => self::variacion($k["ingresos"]["actual"], $k["ingresos"]["anterior"]),
            "boletos" => (int) $k["boletos"]["actual"],
            "ocupacion" => $k["ocupacion"]["actual"] === null ? null : self::porciento((float) $k["ocupacion"]["actual"]),
            "salidas" => (int) $k["salidas"]["actual"],
            "canceladas" => (int) $k["salidasCanceladas"]["actual"],
            "anulados" => (int) $k["anulados"]["actual"],
            "montoAnulado" => Dinero::con((int) $k["montoAnulado"]["actual"], $m),
            "cortesias" => (int) $k["cortesias"]["actual"],
            "noPresentados" => $k["tasaNoPresentados"]["actual"] === null ? null : self::porciento((float) $k["tasaNoPresentados"]["actual"]),
            "quien" => array_filter([
                "Anulaciones" => self::nombres($incidencias["anulados"]),
                "Reasignaciones" => self::nombres($incidencias["reasignados"]),
                "Cortesías" => self::nombres($incidencias["cortesias"]),
            ]),
            "alertas" => array_map(static fn(array $a) => [
                "nivel" => self::NIVELES[$a["nivel"]] ?? $a["nivel"],
                "titulo" => $a["titulo"],
                "detalle" => $a["detalle"],
            ], $alertas),
        ];
    }

    /** @param list<array<string, mixed>> $secciones */
    public static function texto(string $titulo, array $secciones): string
    {
        $lineas = [$titulo];
        foreach ($secciones as $s) {
            $lineas[] = "";
            $lineas[] = (string) $s["empresa"];
            $lineas[] = sprintf("• Ingresos: %s%s · %d boletos", $s["ingresos"], $s["variacion"] !== null ? " ({$s["variacion"]} vs. el mismo día de la semana pasada)" : "", $s["boletos"]);
            $lineas[] = sprintf(
                "• %d salidas%s%s",
                $s["salidas"],
                $s["ocupacion"] !== null ? ", ocupación {$s["ocupacion"]}" : "",
                $s["canceladas"] > 0 ? sprintf(" (%d anuladas)", $s["canceladas"]) : "",
            );
            $lineas[] = sprintf(
                "• Anulados: %d (%s) · Cortesías: %d%s",
                $s["anulados"],
                $s["montoAnulado"],
                $s["cortesias"],
                $s["noPresentados"] !== null ? " · No se presentaron: {$s["noPresentados"]}" : "",
            );
            foreach ($s["quien"] as $que => $nombres) {
                $lineas[] = "• {$que}: {$nombres}";
            }
            if ($s["alertas"] !== []) {
                $lineas[] = "Pide acción:";
                foreach ($s["alertas"] as $a) {
                    $lineas[] = sprintf("• [%s] %s. %s", $a["nivel"], $a["titulo"], $a["detalle"]);
                }
            }
        }

        // El chat corta en 4000: un resumen de muchas empresas se queda en lo primero.
        return mb_strlen($texto = implode("\n", $lineas)) > 3900 ? mb_substr($texto, 0, 3880) . "\n…" : $texto;
    }

    public static function fechaLarga(\DateTimeImmutable $dia): string
    {
        return (new \IntlDateFormatter("es_GT", \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, "America/Guatemala", null, "EEEE d 'de' MMMM"))->format($dia);
    }

    private static function variacion(int|float|null $actual, int|float|null $anterior): ?string
    {
        if ($actual === null || !$anterior) {
            return null;
        }
        $v = (int) round(($actual / $anterior - 1) * 100);

        return ($v > 0 ? "+" : "") . $v . " %";
    }

    private static function porciento(float $v): string
    {
        return (int) round($v * 100) . " %";
    }

    /** @param list<array{nombre: string, usuario: string, boletos: int}> $filas */
    private static function nombres(array $filas): string
    {
        return implode(", ", array_map(static fn(array $f) => sprintf("%s %d", $f["nombre"], $f["boletos"]), $filas));
    }
}
