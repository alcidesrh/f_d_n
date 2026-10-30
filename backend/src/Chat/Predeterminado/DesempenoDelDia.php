<?php

declare(strict_types=1);

namespace App\Chat\Predeterminado;

use App\Reporte\Dinero;
use App\Tablero\ConsultaTablero;

/**
 * El texto de "Desempeño del día" (ADR-045): ingresos del día por boleto
 * (ruta completa e intermedia) y por encomienda, cómo se repartieron por
 * canal, la operación del día (reasignados, anulados, cancelados, cortesías)
 * y el ranking de estaciones. Puro: recibe las filas ya leídas.
 */
final class DesempenoDelDia
{
    /** Cuántas estaciones entran al ranking. */
    public const TOP_ESTACIONES = 5;

    private const CANALES = ["estacion" => "Estaciones", "agencia" => "Agencias", "web" => "Página web"];

    /**
     * @param list<array{tipo: string, nombre: string, intermedia: bool, boletos: int, ingresos: int}> $boletos
     * @param list<array{nombre: string, cantidad: int, ingresos: int}>                                 $encomiendas
     * @param array{anulados: int, reasignados: int, cancelados: int, cortesias: int}                   $operacion
     */
    public static function texto(string $titulo, array $boletos, array $encomiendas, array $operacion): string
    {
        $q = static fn(int $c) => Dinero::con($c, ConsultaTablero::MONEDA);

        $ruta = self::suma(array_filter($boletos, static fn(array $b) => !$b["intermedia"]));
        $intermedia = self::suma(array_filter($boletos, static fn(array $b) => $b["intermedia"]));
        $boletosTotal = self::suma($boletos);
        $enc = self::suma(array_map(static fn(array $e) => ["boletos" => $e["cantidad"], "ingresos" => $e["ingresos"]], $encomiendas));
        $total = $boletosTotal["ingresos"] + $enc["ingresos"];

        if ($boletosTotal["boletos"] === 0 && $enc["boletos"] === 0) {
            return $titulo . "\nHoy no hubo ventas ni encomiendas.";
        }

        $lineas = [
            $titulo,
            "",
            "Ingresos totales: " . $q($total),
            sprintf("• Boletos: %s · %d", $q($boletosTotal["ingresos"]), $boletosTotal["boletos"]),
            sprintf("• Encomiendas: %s · %d", $q($enc["ingresos"]), $enc["boletos"]),
            "",
            "Boletos",
            sprintf("• Ruta completa: %s · %d", $q($ruta["ingresos"]), $ruta["boletos"]),
            sprintf("• Intermedia: %s · %d", $q($intermedia["ingresos"]), $intermedia["boletos"]),
            "",
            "Ingreso por canal",
        ];
        foreach (self::CANALES as $tipo => $nombre) {
            $canal = self::suma(array_filter($boletos, static fn(array $b) => $b["tipo"] === $tipo));
            $lineas[] = sprintf("• %s: %s · %d", $nombre, $q($canal["ingresos"]), $canal["boletos"]);
        }

        $lineas[] = "";
        $lineas[] = "Operación";
        $lineas[] = sprintf(
            "• Reasignados: %d · Anulados: %d · Cancelados: %d · Cortesías: %d",
            $operacion["reasignados"],
            $operacion["anulados"],
            $operacion["cancelados"],
            $operacion["cortesias"],
        );

        $ranking = self::ranking($boletos, $encomiendas);
        if ($ranking !== []) {
            $lineas[] = "";
            $lineas[] = "Estaciones con más ventas";
            $puesto = 0;
            foreach ($ranking as $nombre => $r) {
                $lineas[] = sprintf(
                    "%d. %s · %s (boletos %d · encomiendas %d)",
                    ++$puesto,
                    $nombre,
                    $q($r["ingresos"]),
                    $r["boletos"],
                    $r["encomiendas"],
                );
            }
        }

        return implode("\n", $lineas);
    }

    /**
     * Estaciones por ingreso (boletos y encomiendas juntos), de más a menos.
     *
     * @param list<array{tipo: string, nombre: string, intermedia: bool, boletos: int, ingresos: int}> $boletos
     * @param list<array{nombre: string, cantidad: int, ingresos: int}>                                 $encomiendas
     *
     * @return array<string, array{ingresos: int, boletos: int, encomiendas: int}>
     */
    private static function ranking(array $boletos, array $encomiendas): array
    {
        $r = [];
        foreach ($boletos as $b) {
            if ($b["tipo"] !== "estacion") {
                continue;
            }
            $r[$b["nombre"]] ??= ["ingresos" => 0, "boletos" => 0, "encomiendas" => 0];
            $r[$b["nombre"]]["ingresos"] += $b["ingresos"];
            $r[$b["nombre"]]["boletos"] += $b["boletos"];
        }
        foreach ($encomiendas as $e) {
            $r[$e["nombre"]] ??= ["ingresos" => 0, "boletos" => 0, "encomiendas" => 0];
            $r[$e["nombre"]]["ingresos"] += $e["ingresos"];
            $r[$e["nombre"]]["encomiendas"] += $e["cantidad"];
        }
        uasort($r, static fn(array $a, array $b) => $b["ingresos"] <=> $a["ingresos"]);

        return array_slice(array_filter($r, static fn(array $e) => $e["ingresos"] > 0), 0, self::TOP_ESTACIONES, true);
    }

    /**
     * @param list<array{boletos: int, ingresos: int}> $filas
     *
     * @return array{boletos: int, ingresos: int}
     */
    private static function suma(array $filas): array
    {
        $t = ["boletos" => 0, "ingresos" => 0];
        foreach ($filas as $f) {
            $t["boletos"] += $f["boletos"];
            $t["ingresos"] += $f["ingresos"];
        }

        return $t;
    }
}
