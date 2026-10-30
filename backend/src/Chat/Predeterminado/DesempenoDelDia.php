<?php

declare(strict_types=1);

namespace App\Chat\Predeterminado;

use App\Reporte\Dinero;
use App\Tablero\ConsultaTablero;

/**
 * El desempeño del día (ADR-045): ingresos del día por boleto (ruta completa
 * e intermedia) y por encomienda, cómo se repartieron por canal, la operación
 * del día (reasignados, anulados, cancelados, cortesías) y el ranking de
 * estaciones. Puro: recibe las filas ya leídas y arma las cifras de la tarjeta.
 */
final class DesempenoDelDia
{
    /** Cuántas estaciones entran al ranking. */
    public const TOP_ESTACIONES = 5;

    private const CANALES = ["estacion" => "Estaciones", "agencia" => "Agencias", "web" => "Página web"];

    /**
     * Las cifras ya en texto de moneda, y los porcentajes para las barras.
     *
     * @param list<array{tipo: string, nombre: string, intermedia: bool, boletos: int, ingresos: int}> $boletos
     * @param list<array{nombre: string, cantidad: int, ingresos: int}>                                 $encomiendas
     * @param array{anulados: int, reasignados: int, cancelados: int, cortesias: int, vouchers: int}   $operacion
     *
     * @return array{
     *     titulo: string, empresa: string, vacio: bool, total: string,
     *     boletos: array{ingresos: string, cantidad: int},
     *     encomiendas: array{ingresos: string, cantidad: int},
     *     ruta: array{ingresos: string, cantidad: int, porcentaje: float},
     *     intermedia: array{ingresos: string, cantidad: int, porcentaje: float},
     *     canales: list<array{clave: string, nombre: string, ingresos: string, boletos: int, porcentaje: float}>,
     *     operacion: array{reasignados: int, anulados: int, cancelados: int, cortesias: int, vouchers: int},
     *     ranking: list<array{nombre: string, ingresos: string, boletos: int, encomiendas: int, porcentaje: float}>
     * }
     */
    public static function datos(string $titulo, string $empresa, array $boletos, array $encomiendas, array $operacion): array
    {
        $q = static fn(int $c) => Dinero::con($c, ConsultaTablero::MONEDA);

        $ruta = self::suma(array_filter($boletos, static fn(array $b) => !$b["intermedia"]));
        $intermedia = self::suma(array_filter($boletos, static fn(array $b) => $b["intermedia"]));
        $boletosTotal = self::suma($boletos);
        $enc = self::suma(array_map(static fn(array $e) => ["boletos" => $e["cantidad"], "ingresos" => $e["ingresos"]], $encomiendas));
        $total = $boletosTotal["ingresos"] + $enc["ingresos"];

        $canales = [];
        foreach (self::CANALES as $tipo => $nombre) {
            $canal = self::suma(array_filter($boletos, static fn(array $b) => $b["tipo"] === $tipo));
            $canales[] = [
                "clave" => $tipo,
                "nombre" => $nombre,
                "ingresos" => $q($canal["ingresos"]),
                "boletos" => $canal["boletos"],
                "porcentaje" => self::porcentaje($canal["ingresos"], $boletosTotal["ingresos"]),
            ];
        }

        $ranking = self::ranking($boletos, $encomiendas);
        $maximo = $ranking === [] ? 0 : max(array_column($ranking, "ingresos"));

        return [
            "titulo" => $titulo,
            "empresa" => $empresa,
            "vacio" => $boletosTotal["boletos"] === 0 && $enc["boletos"] === 0,
            "total" => $q($total),
            "boletos" => ["ingresos" => $q($boletosTotal["ingresos"]), "cantidad" => $boletosTotal["boletos"]],
            "encomiendas" => ["ingresos" => $q($enc["ingresos"]), "cantidad" => $enc["boletos"]],
            "ruta" => [
                "ingresos" => $q($ruta["ingresos"]),
                "cantidad" => $ruta["boletos"],
                "porcentaje" => self::porcentaje($ruta["ingresos"], $boletosTotal["ingresos"]),
            ],
            "intermedia" => [
                "ingresos" => $q($intermedia["ingresos"]),
                "cantidad" => $intermedia["boletos"],
                "porcentaje" => self::porcentaje($intermedia["ingresos"], $boletosTotal["ingresos"]),
            ],
            "canales" => $canales,
            "operacion" => [
                "reasignados" => $operacion["reasignados"],
                "anulados" => $operacion["anulados"],
                "cancelados" => $operacion["cancelados"],
                "cortesias" => $operacion["cortesias"],
                "vouchers" => $operacion["vouchers"],
            ],
            "ranking" => array_map(static fn(string $nombre, array $r) => [
                "nombre" => $nombre,
                "ingresos" => $q($r["ingresos"]),
                "boletos" => $r["boletos"],
                "encomiendas" => $r["encomiendas"],
                "porcentaje" => self::porcentaje($r["ingresos"], $maximo),
            ], array_keys($ranking), array_values($ranking)),
        ];
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

    /** Porcentaje de `$parte` sobre `$todo`, con dos decimales; 0 si no hay base. */
    private static function porcentaje(int $parte, int $todo): float
    {
        return $todo > 0 ? round(100 * $parte / $todo, 2) : 0.0;
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
