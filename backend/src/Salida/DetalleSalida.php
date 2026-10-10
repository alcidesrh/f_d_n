<?php

declare(strict_types=1);

namespace App\Salida;

use App\Entity\Salida;
use App\Salida\Manifiesto\Manifiesto;
use App\Salida\Manifiesto\ManifiestoSalida;
use App\Venta\Boleto\DatosBoleto;
use App\Venta\ConsultaVenta;
use App\Venta\Itinerarios;
use Money\Money;

/**
 * Lo que muestra "Ver" en la gestión de salidas: la salida con sus paradas
 * y croquis (como la taquilla), el estado de cada asiento para el viaje
 * completo y un resumen de la venta (por clase, por canal, ingresos).
 */
final class DetalleSalida
{
    public function __construct(
        private readonly ConsultaVenta $consulta,
        private readonly Itinerarios $itinerarios,
        private readonly ManifiestoSalida $manifiestos,
    ) {}

    /** @return array<string, mixed> */
    public function de(Salida $salida): array
    {
        $detalle = $this->consulta->detalle($salida);
        $ocupados = $this->consulta->ocupacion(
            $salida,
            $this->itinerarios->deTrayecto($salida->getTrayecto())->completo(),
        );
        $manifiesto = $this->manifiestos->de($salida);

        return [
            ...$detalle,
            "ocupados" => $ocupados,
            "pilotos" => $manifiesto->pilotos,
            "creadaPor" => self::quien($salida->getCreatedBy()),
            "creadaEn" => $salida->getCreatedAt()?->format(DATE_ATOM),
            "resumen" => self::resumen($detalle["croquis"], $ocupados, $manifiesto),
        ];
    }

    /** Nombre y apellido del usuario, o su usuario si no los tiene. */
    public static function quien(?\App\Entity\Usuario $u): ?string
    {
        if ($u === null) {
            return null;
        }
        $nombre = trim(($u->getNombre() ?? "") . " " . ($u->getApellido() ?? ""));

        return $nombre !== "" ? $nombre : $u->getUsername();
    }

    /**
     * @param list<array<string, mixed>>                                                    $croquis
     * @param list<array{asiento: int, estado: string, canal: ?string, sinCobro: ?string}> $ocupados
     *
     * @return array<string, mixed>
     */
    public static function resumen(array $croquis, array $ocupados, Manifiesto $manifiesto): array
    {
        $estados = array_column($ocupados, null, "asiento");
        $clases = [];
        foreach ($croquis as $e) {
            if (($e["tipo"] ?? null) !== "asiento") {
                continue;
            }
            $c = &$clases[$e["clase"]];
            $c ??= ["clase" => $e["clase"], "asientos" => 0, "vendidos" => 0, "reservados" => 0];
            ++$c["asientos"];
            match ($estados[$e["id"]]["estado"] ?? null) {
                "vendido" => ++$c["vendidos"],
                "reservado" => ++$c["reservados"],
                default => null,
            };
            unset($c);
        }
        ksort($clases);

        $vendidos = array_filter($ocupados, static fn(array $o) => $o["estado"] === "vendido");
        $contar = static fn(callable $criterio): int => count(array_filter($vendidos, $criterio));
        $moneda = $manifiesto->moneda();
        $porEstado = [];
        foreach ($manifiesto->pasajeros as $p) {
            $porEstado[$p->estado] = ($porEstado[$p->estado] ?? 0) + 1;
        }

        return [
            "clases" => array_values($clases),
            "asientos" => array_sum(array_column($clases, "asientos")),
            "vendidos" => count($vendidos),
            "reservados" => count($ocupados) - count($vendidos),
            "canales" => [
                "estacion" => $contar(static fn(array $o) => $o["canal"] === "estacion" && !$o["sinCobro"]),
                "agencia" => $contar(static fn(array $o) => $o["canal"] === "agencia" && !$o["sinCobro"]),
                "web" => $contar(static fn(array $o) => $o["canal"] === "web" && !$o["sinCobro"]),
                "cortesia" => $contar(static fn(array $o) => $o["sinCobro"] === "cortesia"),
                "voucher" => $contar(static fn(array $o) => $o["sinCobro"] === "voucher"),
            ],
            "boletosPorEstado" => $porEstado,
            "ingresos" => DatosBoleto::importe(new Money(Manifiesto::cobrado($manifiesto->pasajeros), new \Money\Currency($moneda))),
        ];
    }
}
