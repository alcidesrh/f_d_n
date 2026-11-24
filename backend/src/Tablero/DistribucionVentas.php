<?php

declare(strict_types=1);

namespace App\Tablero;

use App\Entity\Enum\EstadoBoletoAsiento;

/**
 * Cómo se reparte lo vendido en unos días (puro): por canal, por punto de
 * venta (cada estación por su sigla; la página y las agencias como dos
 * puntos más), por usuario y, en boletos, por tipo. Lo vendido es lo que el
 * tablero cuenta como ingreso: boletos cobrados (ni cortesía ni voucher) que
 * no se anularon ni se reasignaron, los no presentados incluidos. Importes en
 * centavos; cada lista viene de mayor a menor.
 */
final class DistribucionVentas
{
    /** @var list<string> orden fijo (el de los colores del tablero) */
    public const CANALES = ["estacion", "agencia", "web"];

    /** @var list<string> */
    public const TIPOS = ["cobrado", "anulado", "reasignado", "cortesia", "cancelado", "voucher"];

    /** Tipos que cuentan como venta. */
    private const VENDIDOS = ["cobrado", "cancelado"];

    /**
     * @param list<array{canal: string, estacionId: ?int, sigla: ?string, estacion: ?string, usuarioId: ?int, usuario: ?string, nombre: ?string, estado: string, cortesia: bool, voucher: bool, boletos: int, monto: int}> $filas
     *        boletos agrupados por punto de venta, usuario y lo que define su tipo
     */
    public function __construct(
        private readonly FiltroDistribucion $filtro,
        private readonly array $filas,
    ) {}

    /**
     * Cada boleto en un solo tipo. Anulados y reasignados por su estado (no
     * cuentan como venta en ningún lado); de los demás, cortesías y vouchers
     * por no cobrarse, aunque luego no se presenten; los cobrados que no
     * abordaron son cancelados. Los pospuestos de la página siguen cobrados.
     * Mismo criterio que `ConsultaTablero::SIN_COBRO`: lo vendido por una
     * agencia siempre se cobra (sus ventas migradas traen `voucher`).
     */
    public static function tipo(string $estado, bool $cortesia, bool $voucher, string $canal): string
    {
        return match (true) {
            $estado === EstadoBoletoAsiento::ANULADO->value => "anulado",
            $estado === EstadoBoletoAsiento::REASIGNADO->value => "reasignado",
            $cortesia => "cortesia",
            $voucher && $canal !== "agencia" => "voucher",
            $estado === EstadoBoletoAsiento::CANCELADO->value => "cancelado",
            default => "cobrado",
        };
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $vacio = ["monto" => 0, "boletos" => 0];
        $total = $vacio;
        $canales = array_fill_keys(self::CANALES, $vacio);
        $tipos = array_fill_keys(self::TIPOS, $vacio);
        $estaciones = [];
        $usuarios = [];

        foreach ($this->filas as $f) {
            $tipo = self::tipo($f["estado"], $f["cortesia"], $f["voucher"], $f["canal"]);
            $tipos[$tipo]["monto"] += $f["monto"];
            $tipos[$tipo]["boletos"] += $f["boletos"];
            if (!in_array($tipo, self::VENDIDOS, true)) {
                continue;
            }
            $venta = ["monto" => $f["monto"], "boletos" => $f["boletos"]];
            self::sumar($total, $venta);
            self::sumar($canales[$f["canal"]], $venta);

            $punto = self::punto($f);
            $estaciones[$punto["clave"]] ??= $punto + $vacio;
            self::sumar($estaciones[$punto["clave"]], $venta);

            $clave = "usuario-" . ($f["usuarioId"] ?? 0);
            $usuarios[$clave] ??= [
                "clave" => $clave,
                "usuario" => $f["usuario"] ?? "—",
                "nombre" => $f["nombre"] ?? "Sin usuario",
            ] + $vacio;
            self::sumar($usuarios[$clave], $venta);
        }

        return [
            "desde" => $this->filtro->desde->format("Y-m-d"),
            "hasta" => $this->filtro->hasta->format("Y-m-d"),
            "total" => $total,
            "canales" => array_map(static fn(string $c) => ["canal" => $c] + $canales[$c], self::CANALES),
            "estaciones" => self::ordenadas($estaciones, "sigla"),
            "usuarios" => self::ordenadas($usuarios, "usuario"),
            "tipos" => array_map(static fn(string $t) => ["tipo" => $t] + $tipos[$t], self::TIPOS),
        ];
    }

    /**
     * El punto de venta de una fila: la estación por su sigla (o su nombre, si
     * no tiene), o la página o las agencias, cada una como un punto.
     *
     * @param array{canal: string, estacionId: ?int, sigla: ?string, estacion: ?string} $f
     * @return array{clave: string, canal: string, sigla: string, nombre: string}
     */
    private static function punto(array $f): array
    {
        return match ($f["canal"]) {
            "web" => ["clave" => "web", "canal" => "web", "sigla" => "Página", "nombre" => "Página web"],
            "agencia" => ["clave" => "agencia", "canal" => "agencia", "sigla" => "Agencias", "nombre" => "Agencias"],
            default => [
                "clave" => "estacion-" . ($f["estacionId"] ?? 0),
                "canal" => "estacion",
                "sigla" => $f["sigla"] ?? $f["estacion"] ?? "—",
                "nombre" => $f["estacion"] ?? "Sin estación",
            ],
        };
    }

    /** @param array{monto: int, boletos: int} $a */
    private static function sumar(array &$a, array $b): void
    {
        $a["monto"] += $b["monto"];
        $a["boletos"] += $b["boletos"];
    }

    /**
     * Las que vendieron algo, de mayor a menor (a igual monto, por nombre).
     *
     * @param array<string, array{monto: int, boletos: int}&array<string, mixed>> $grupos
     * @return list<array<string, mixed>>
     */
    private static function ordenadas(array $grupos, string $nombre): array
    {
        $r = array_values(array_filter($grupos, static fn(array $g) => $g["monto"] > 0));
        usort($r, static fn(array $a, array $b) => [$b["monto"], $a[$nombre]] <=> [$a["monto"], $b[$nombre]]);

        return $r;
    }
}
