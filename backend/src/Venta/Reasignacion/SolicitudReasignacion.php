<?php

declare(strict_types=1);

namespace App\Venta\Reasignacion;

use App\Venta\Excepcion\VentaRechazada;

/**
 * Pedido de reasignación (`POST /api/venta/boletos/reasignar`): los boletos
 * de una venta pasan a otros asientos (de la misma salida o de otra). El
 * boleto `boletos[i]` pasa al asiento `asientos[i]`.
 */
final readonly class SolicitudReasignacion
{
    /**
     * @param list<int> $boletos  boletos que se reasignan
     * @param list<int> $asientos asientos nuevos, uno por boleto y en el mismo orden
     */
    public function __construct(
        public array $boletos,
        public int $salidaId,
        /** Trayecto que viaja ahora; null = el de la salida. */
        public ?int $trayectoId,
        public array $asientos,
        /** Cobrar la tarifa del trayecto completo para igualar el precio original. */
        public bool $cobrarTrayectoCompleto = false,
    ) {}

    /**
     * @param array<string, mixed> $datos
     */
    public static function desdeArray(array $datos): self
    {
        $entero = static function (mixed $v, string $campo, bool $requerido = true): ?int {
            if ($v === null || $v === "") {
                if ($requerido) {
                    throw new VentaRechazada("Falta el campo «{$campo}».");
                }

                return null;
            }
            if (!is_numeric($v) || (int) $v <= 0) {
                throw new VentaRechazada("El campo «{$campo}» no es válido.");
            }

            return (int) $v;
        };
        $lista = static fn(string $campo) => array_values(array_map(
            static fn(mixed $v, int|string $i) => $entero($v, "{$campo}[{$i}]"),
            (array) ($datos[$campo] ?? []),
            array_keys((array) ($datos[$campo] ?? [])),
        ));

        $boletos = $lista("boletos");
        $asientos = $lista("asientos");
        if ($boletos === []) {
            throw new VentaRechazada("Elija al menos un boleto.");
        }
        if (count($boletos) > 60) {
            throw new VentaRechazada("Demasiados boletos en una sola reasignación.");
        }
        if (count($boletos) !== count($asientos)) {
            throw new VentaRechazada(sprintf("Elija %d asiento(s) nuevo(s), uno por boleto.", count($boletos)));
        }
        if (count($boletos) !== count(array_unique($boletos)) || count($asientos) !== count(array_unique($asientos))) {
            throw new VentaRechazada("Hay boletos o asientos repetidos.");
        }

        return new self(
            boletos: $boletos,
            salidaId: $entero($datos["salida"] ?? null, "salida"),
            trayectoId: $entero($datos["trayecto"] ?? null, "trayecto", false),
            asientos: $asientos,
            cobrarTrayectoCompleto: (bool) ($datos["cobrarTrayectoCompleto"] ?? false),
        );
    }
}
