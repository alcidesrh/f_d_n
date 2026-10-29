<?php

declare(strict_types=1);

namespace App\Chat\Predeterminado;

use App\Entity\Enum\EstadoBoletoAsiento;

/**
 * Cómo iba una salida al iniciar (ADR-045, "Salida iniciada"). Puro: se arma
 * con los boletos de la salida ya leídos.
 *
 * - Ocupado: un asiento con al menos un boleto vivo (los que liberan el
 *   asiento —anulado, reasignado, cancelado, pospuesto— no cuentan). Un
 *   asiento vendido solo en tramos intermedios también está ocupado.
 * - Intermedia: un boleto vivo de un tramo que no va de la primera a la
 *   última parada.
 * - Las filas son de quien vendió (el usuario de la venta; el boleto
 *   reasignado sigue en la misma venta). Una fila aparece si vendió al menos
 *   un boleto de la salida, aunque se haya anulado. Las columnas se solapan:
 *   un boleto puede ser cortesía, intermedio y clase B a la vez; el total es
 *   A + B (los vivos).
 */
final readonly class OcupacionAlIniciar
{
    public const COLUMNAS = [
        "reasignados" => "Reasignados",
        "anulados" => "Anulados",
        "vouchers" => "Vouchers",
        "cortesias" => "Cortesías",
        "intermedias" => "Intermedias",
        "claseA" => "Clase A",
        "claseB" => "Clase B",
        "total" => "Total",
    ];

    /**
     * @param array<string, int>                                    $porCanal estacion, agencia, web: boletos vivos
     * @param list<array{usuario: string, cuenta: array<string, int>}> $filas    por quien vendió, de más a menos
     * @param array<string, int>                                    $totales  por columna
     */
    private function __construct(
        public int $asientos,
        public int $ocupados,
        public int $conIntermedias,
        public array $porCanal,
        public array $filas,
        public array $totales,
    ) {}

    public function libres(): int
    {
        return max(0, $this->asientos - $this->ocupados);
    }

    /**
     * @param list<array{asiento: int, estado: string, canal: string, usuario: string, voucher: bool, cortesia: bool, clase: string, sube: int, baja: int, reasignado: bool}> $boletos
     * @param int $ultimaParada orden de la última parada de la ruta
     */
    public static function de(array $boletos, int $asientosDelBus, int $ultimaParada): self
    {
        $liberan = EstadoBoletoAsiento::liberanAsiento();
        $ocupados = [];
        $intermedios = [];
        $porCanal = ["estacion" => 0, "agencia" => 0, "web" => 0];
        $vacia = array_fill_keys(array_keys(self::COLUMNAS), 0);
        $filas = [];
        foreach ($boletos as $b) {
            $fila = &$filas[$b["usuario"]];
            $fila ??= $vacia;
            if ($b["estado"] === EstadoBoletoAsiento::ANULADO->value) {
                $fila["anulados"]++;
            }
            if (in_array($b["estado"], $liberan, true)) {
                unset($fila);
                continue;
            }
            $intermedia = $b["sube"] > 0 || $b["baja"] < $ultimaParada;
            $ocupados[$b["asiento"]] = true;
            if ($intermedia) {
                $intermedios[$b["asiento"]] = true;
            }
            $porCanal[$b["canal"]] = ($porCanal[$b["canal"]] ?? 0) + 1;
            $fila["reasignados"] += (int) $b["reasignado"];
            $fila["vouchers"] += (int) $b["voucher"];
            $fila["cortesias"] += (int) $b["cortesia"];
            $fila["intermedias"] += (int) $intermedia;
            $fila[$b["clase"] === "B" ? "claseB" : "claseA"]++;
            $fila["total"]++;
            unset($fila);
        }
        $lista = [];
        foreach ($filas as $usuario => $cuenta) {
            $lista[] = ["usuario" => (string) $usuario, "cuenta" => $cuenta];
        }
        usort($lista, static fn(array $a, array $b) => [$b["cuenta"]["total"], $a["usuario"]] <=> [$a["cuenta"]["total"], $b["usuario"]]);
        $totales = $vacia;
        foreach ($lista as $f) {
            foreach ($f["cuenta"] as $k => $n) {
                $totales[$k] += $n;
            }
        }

        return new self($asientosDelBus, count($ocupados), count($intermedios), $porCanal, $lista, $totales);
    }

    /**
     * El mensaje del chat: lo esencial en texto; la tabla completa va en el PDF.
     *
     * @param string $inicio hora de inicio ya formateada
     */
    public function texto(string $ruta, string $inicio, ?string $programada, ?string $bus): string
    {
        $lineas = [
            sprintf("Salida iniciada: %s", $ruta),
            sprintf("Inició: %s%s%s", $inicio, $programada !== null ? sprintf(" (programada %s)", $programada) : "", $bus !== null ? sprintf(" · Bus %s", $bus) : ""),
            sprintf(
                "Asientos: %d ocupados%s y %d libres de %d.",
                $this->ocupados,
                $this->conIntermedias > 0 ? sprintf(" (%d con tramos intermedios)", $this->conIntermedias) : "",
                $this->libres(),
                $this->asientos,
            ),
            sprintf("Por canal: estación %d · agencia %d · web %d.", $this->porCanal["estacion"] ?? 0, $this->porCanal["agencia"] ?? 0, $this->porCanal["web"] ?? 0),
        ];
        if ($this->filas !== []) {
            $lineas[] = "Por vendedor:";
            foreach ($this->filas as $f) {
                $lineas[] = "• " . self::lineaVendedor($f["usuario"], $f["cuenta"]);
            }
            $lineas[] = "La tabla completa va en el PDF.";
        }

        return implode("\n", $lineas);
    }

    /** @param array<string, int> $c */
    private static function lineaVendedor(string $usuario, array $c): string
    {
        $extra = array_filter([
            $c["reasignados"] > 0 ? self::cuenta($c["reasignados"], "reasignado", "reasignados") : null,
            $c["anulados"] > 0 ? self::cuenta($c["anulados"], "anulado", "anulados") : null,
            $c["vouchers"] > 0 ? self::cuenta($c["vouchers"], "voucher", "vouchers") : null,
            $c["cortesias"] > 0 ? self::cuenta($c["cortesias"], "cortesía", "cortesías") : null,
            $c["intermedias"] > 0 ? self::cuenta($c["intermedias"], "intermedia", "intermedias") : null,
        ]);

        return sprintf("%s: %d (A %d, B %d)%s", $usuario, $c["total"], $c["claseA"], $c["claseB"], $extra === [] ? "" : " · " . implode(" · ", $extra));
    }

    private static function cuenta(int $n, string $uno, string $varios): string
    {
        return sprintf("%d %s", $n, $n === 1 ? $uno : $varios);
    }
}
