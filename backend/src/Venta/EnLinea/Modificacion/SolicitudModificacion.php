<?php

declare(strict_types=1);

namespace App\Venta\EnLinea\Modificacion;

use App\Venta\Excepcion\VentaRechazada;

/**
 * Lo que el cliente pide cambiar de un viaje comprado en la página:
 *
 * - `cambios`: por cada asiento comprado que cambia, a qué salida, trayecto
 *   (la ruta: par de paradas) y asiento pasa. Cambiar de clase es elegir un
 *   asiento de la otra clase.
 * - `nuevos`: asientos que se agregan, cada uno en su salida y trayecto.
 *
 * No hay forma de quitar asientos: el cliente no anula ni cancela compras.
 */
final readonly class SolicitudModificacion
{
    public const MAX_ASIENTOS = 10;

    /**
     * @param list<array{boleto: int, salida: int, trayecto: ?int, asiento: int}> $cambios
     * @param list<array{salida: int, trayecto: ?int, asiento: int}>               $nuevos
     */
    public function __construct(
        public array $cambios,
        public array $nuevos,
    ) {}

    /**
     * @param array<string, mixed> $datos `{ cambios: [{boleto, salida, trayecto?, asiento}], nuevos: [{salida, trayecto?, asiento}] }`
     *
     * @throws VentaRechazada
     */
    public static function desdeArray(array $datos): self
    {
        $entero = static function (mixed $v, string $campo, bool $requerido = true): ?int {
            if ($v === null || $v === "") {
                return $requerido ? throw new VentaRechazada("Falta el campo «{$campo}».", "modificacion_invalida") : null;
            }
            $n = is_scalar($v) ? filter_var($v, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) : false;
            if ($n === false) {
                throw new VentaRechazada("El campo «{$campo}» no es válido.", "modificacion_invalida");
            }

            return $n;
        };
        $lista = static fn(mixed $v): array => is_array($v) ? array_values($v) : [];

        $cambios = [];
        foreach ($lista($datos["cambios"] ?? []) as $i => $c) {
            $c = is_array($c) ? $c : [];
            $cambios[] = [
                "boleto" => $entero($c["boleto"] ?? null, "cambios[{$i}].boleto"),
                "salida" => $entero($c["salida"] ?? null, "cambios[{$i}].salida"),
                "trayecto" => $entero($c["trayecto"] ?? null, "cambios[{$i}].trayecto", false),
                "asiento" => $entero($c["asiento"] ?? null, "cambios[{$i}].asiento"),
            ];
        }
        $nuevos = [];
        foreach ($lista($datos["nuevos"] ?? []) as $i => $n) {
            $n = is_array($n) ? $n : [];
            $nuevos[] = [
                "salida" => $entero($n["salida"] ?? null, "nuevos[{$i}].salida"),
                "trayecto" => $entero($n["trayecto"] ?? null, "nuevos[{$i}].trayecto", false),
                "asiento" => $entero($n["asiento"] ?? null, "nuevos[{$i}].asiento"),
            ];
        }

        if ($cambios === [] && $nuevos === []) {
            throw new VentaRechazada("No hay cambios que aplicar.", "sin_cambio");
        }
        if (count($cambios) + count($nuevos) > self::MAX_ASIENTOS) {
            throw new VentaRechazada(sprintf("A lo sumo %d asientos por modificación.", self::MAX_ASIENTOS), "modificacion_invalida");
        }
        $boletos = array_column($cambios, "boleto");
        if (count($boletos) !== count(array_unique($boletos))) {
            throw new VentaRechazada("Un asiento comprado solo puede cambiar una vez por modificación.", "modificacion_invalida");
        }
        // Un mismo asiento de una salida no puede ser el destino de dos boletos.
        $destinos = array_map(static fn(array $x) => $x["salida"] . "-" . $x["asiento"], [...$cambios, ...$nuevos]);
        if (count($destinos) !== count(array_unique($destinos))) {
            throw new VentaRechazada("Eligió el mismo asiento dos veces.", "modificacion_invalida");
        }

        return new self($cambios, $nuevos);
    }

    /** @return list<int> salidas de destino, en orden (para bloquearlas sin interbloqueos) */
    public function salidasDestino(): array
    {
        $ids = array_values(array_unique([...array_column($this->cambios, "salida"), ...array_column($this->nuevos, "salida")]));
        sort($ids);

        return $ids;
    }

    /** @return array{cambios: list<array<string, ?int>>, nuevos: list<array<string, ?int>>} */
    public function toArray(): array
    {
        return ["cambios" => $this->cambios, "nuevos" => $this->nuevos];
    }
}
