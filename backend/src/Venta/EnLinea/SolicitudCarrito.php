<?php

declare(strict_types=1);

namespace App\Venta\EnLinea;

use App\Venta\Excepcion\VentaRechazada;

/**
 * Lo que la página aparta al pulsar "Pagar asientos" (ADR-023): la ida y,
 * en viajes de ida y vuelta, el regreso; cada uno con sus asientos. Valida
 * la forma; las reglas de negocio (vendible, libre, tarifa) son de `Reservas`.
 */
final readonly class SolicitudCarrito
{
    public const MAX_VIAJES = 2;
    public const MAX_ASIENTOS = 10;

    /** @param list<array{salida: int, trayecto: ?int, asientos: list<int>}> $viajes en orden: ida, regreso */
    private function __construct(public array $viajes) {}

    /**
     * `[{ salida, trayecto?, asientos: [ids] }, ...]`
     *
     * @param array<mixed> $datos
     *
     * @throws VentaRechazada
     */
    public static function desdeArray(array $datos): self
    {
        if ($datos === [] || !array_is_list($datos) || count($datos) > self::MAX_VIAJES) {
            throw new VentaRechazada("Elija los asientos de la ida (y del regreso, si es ida y vuelta).", "carrito_invalido");
        }
        $viajes = [];
        foreach ($datos as $v) {
            $v = is_array($v) ? $v : [];
            $salida = (int) ($v["salida"] ?? 0);
            $asientos = array_values(array_map("intval", is_array($v["asientos"] ?? null) ? $v["asientos"] : []));
            if ($salida <= 0) {
                throw new VentaRechazada("Falta la salida del viaje.", "carrito_invalido");
            }
            if ($asientos === [] || in_array(0, $asientos, true)) {
                throw new VentaRechazada("Elija al menos un asiento en cada viaje.", "carrito_sin_asientos");
            }
            if (count($asientos) > self::MAX_ASIENTOS) {
                throw new VentaRechazada(sprintf("Puede comprar hasta %d asientos por viaje.", self::MAX_ASIENTOS), "carrito_lleno");
            }
            if (count($asientos) !== count(array_unique($asientos))) {
                throw new VentaRechazada("Hay asientos repetidos.", "carrito_invalido");
            }
            $viajes[] = [
                "salida" => $salida,
                "trayecto" => isset($v["trayecto"]) && (int) $v["trayecto"] > 0 ? (int) $v["trayecto"] : null,
                "asientos" => $asientos,
            ];
        }
        if (count($viajes) === 2 && $viajes[0]["salida"] === $viajes[1]["salida"]) {
            throw new VentaRechazada("La ida y el regreso no pueden ser la misma salida.", "carrito_invalido");
        }

        return new self($viajes);
    }

    /** Ids de salida en orden ascendente: bloquearlas siempre en el mismo orden evita interbloqueos. */
    public function salidasParaBloquear(): array
    {
        $ids = array_map(static fn(array $v) => $v["salida"], $this->viajes);
        sort($ids);

        return $ids;
    }
}
